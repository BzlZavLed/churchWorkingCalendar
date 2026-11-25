<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchMember;
use App\Services\MemberDateFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberBirthDateCorrectionController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->church_id && !$user->isSuperAdmin() && $user->hasCapability('members.manage'), 403);
        $input = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'preview_token' => ['sometimes', 'required', 'string', 'size:64'],
        ]);
        $rows = $this->readDates($input['file']->getRealPath());

        return DB::transaction(function () use ($user, $rows, $input) {
            Church::whereKey($user->church_id)->lockForUpdate()->firstOrFail();
            $members = ChurchMember::where('church_id', $user->church_id)
                ->whereIn('source_person_id', array_keys($rows))->lockForUpdate()->get()->keyBy('source_person_id');
            $preview = [];
            foreach ($rows as $personId => $original) {
                $member = $members->get($personId);
                $current = $member?->data['BornOn'] ?? null;
                $corrected = $this->correctedDate($original, $current);
                $reversed = $corrected ? substr($corrected, 0, 4).'-'.substr($corrected, 8, 2).'-'.substr($corrected, 5, 2) : null;
                $status = match (true) {
                    !$member => 'unmatched',
                    !$corrected => 'invalid',
                    $current === $corrected => 'unchanged',
                    $current !== $reversed => 'different',
                    default => 'ready',
                };
                $preview[] = ['person_id' => (string) $personId, 'id' => $member?->id, 'name' => $member?->name,
                    'current' => $current, 'original' => $original, 'corrected' => $corrected, 'status' => $status];
            }
            $token = hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR));
            if (!isset($input['preview_token'])) {
                return response()->json(['rows' => $preview, 'preview_token' => $token])->header('Cache-Control', 'no-store');
            }
            abort_unless(hash_equals($token, $input['preview_token']), 409, 'Member records changed. Review the file again.');
            $updated = 0;
            foreach ($preview as $row) {
                if ($row['status'] !== 'ready') {
                    continue;
                }
                $member = $members->get($row['person_id']);
                $member->data = array_replace($member->data, ['BornOn' => $row['corrected']]);
                $member->saveQuietly();
                $updated++;
            }
            return ['updated' => $updated];
        });
    }

    private function correctedDate(string $original, ?string $current): ?string
    {
        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{2})$~D', $original, $parts)) {
            if (!$current || !preg_match('/^(\d{4})-\d{2}-\d{2}$/D', $current, $saved)
                || substr($saved[1], -2) !== $parts[3]) {
                return null;
            }

            $original = "{$parts[1]}/{$parts[2]}/{$saved[1]}";
        }

        return MemberDateFormat::normalize($original, 'ydm', fromImport: true);
    }

    private function readDates(string $path): array
    {
        $content = file_get_contents($path);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $file = new \SplTempFileObject();
        $file->fwrite(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        $file->rewind();
        $headers = $file->fgetcsv(',', '"', '') ?: [];
        $person = array_search('PersonId', $headers, true);
        $birth = array_search('BornOn', $headers, true);
        if ($person === false || $birth === false || count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => ['The CSV must contain unique PersonId and BornOn headers.']]);
        }
        $rows = [];
        $line = 1;
        while (!$file->eof()) {
            $row = $file->fgetcsv(',', '"', '');
            if (++$line > 1002) {
                throw ValidationException::withMessages(['file' => ['Maximum 1,000 rows per CSV.']]);
            }
            if ($row === [null] || $row === false) {
                continue;
            }
            $id = trim($row[$person] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($rows[$id]) || count($row) !== count($headers)) {
                throw ValidationException::withMessages(['file' => ["Check the column count and unique PersonId on row {$line}."]]);
            }
            $rows[$id] = trim($row[$birth] ?? '');
        }
        if (!$rows) {
            throw ValidationException::withMessages(['file' => ['No rows with PersonId were found.']]);
        }
        return $rows;
    }
}

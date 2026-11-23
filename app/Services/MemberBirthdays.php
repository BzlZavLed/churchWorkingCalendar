<?php

namespace App\Services;

use App\Models\ChurchMember;
use App\Models\MemberTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class MemberBirthdays
{
    public function roster(int $church, array $input): array
    {
        $fields = MemberTemplate::where('church_id', $church)->orderBy('version')->get()
            ->flatMap(fn ($t) => $t->fields)->keyBy('id')->filter(fn ($f) => $f['type'] === 'dob')->values();
        $field = $input['field_id'] ?? ($fields->contains('id','BornOn') ? 'BornOn' : ($fields->count() === 1 ? $fields[0]['id'] : null));
        if ($field && ! $fields->contains('id', $field)) {
            throw ValidationException::withMessages(['field_id' => ['Choose a date-of-birth field from this church.']]);
        }
        $start = CarbonImmutable::parse($input['date'], $input['timezone'])->startOfDay();
        $end = match ($input['frequency']) {
            'weekly' => $start->addDays(6), 'biweekly' => $start->addDays(13),
            'monthly' => $start->endOfMonth()->startOfDay(), default => $start,
        };
        if ($input['frequency'] === 'monthly') {
            $start = $start->startOfMonth();
        }
        $members = [];
        $missing = 0;
        if ($field) {
            foreach (ChurchMember::where('church_id', $church)->orderBy('name')->cursor() as $member) {
                $value = $member->data[$field] ?? null;
                if(!$value && $field==='BornOn') foreach($fields as $legacy) { if(!empty($member->data[$legacy['id']])) { $value=$member->data[$legacy['id']]; break; } }
                if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    $missing++;

                    continue;
                }
                for ($year = $start->year; $year <= $end->year; $year++) {
                    // February 29 birthdays are celebrated on February 28 in non-leap years.
                    $day = (int) $parts[3];
                    $month = (int) $parts[2];
                    if ($month === 2 && $day === 29 && ! checkdate(2, 29, $year)) {
                        $day = 28;
                    }
                    $birthday = CarbonImmutable::create($year, $month, $day, 0, 0, 0, $input['timezone']);
                    if ($birthday->betweenIncluded($start, $end)) {
                        $members[] = ['id' => $member->id, 'name' => $member->name, 'birthday' => $birthday->toDateString()];
                    }
                }
            }
        }
        usort($members, fn ($a, $b) => [$a['birthday'], $a['name']] <=> [$b['birthday'], $b['name']]);

        return ['fields' => $fields, 'field_id' => $field, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'members' => $members, 'missing_dates' => $missing,
            'ai_available' => (bool) (app(ChurchIntegrations::class)->ai($church) && config('assistant.openai_api_key') && config('assistant.openai_model'))];
    }
}

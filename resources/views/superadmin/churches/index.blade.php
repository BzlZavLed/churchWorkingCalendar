<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Churches</title>
    <link rel="stylesheet" href="{{ asset('css/superadmin.css') }}">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
</head>
<body>
    <main class="container py-5">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2">
            <h1 class="h3 m-0">Churches</h1>
            <form method="post" action="{{ route('superadmin.logout') }}">
                @csrf
                <button class="btn btn-outline-dark" type="submit">Logout</button>
            </form>
        </div>

        @if (session('invite_code'))
            <div class="alert alert-success">
                Invite code: <strong>{{ session('invite_code') }}</strong>
            </div>
        @endif

        <div class="mb-3">
            <a class="btn btn-primary" href="{{ route('superadmin.churches.create') }}">Create Church</a>
        </div>

        <div class="table-responsive bg-white border rounded">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($churches as $church)
                        <tr>
                            <td>{{ $church->id }}</td>
                            <td>{{ $church->name }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('superadmin.churches.edit', $church) }}">Edit</a>
                                    <form method="post" action="{{ route('superadmin.churches.destroy', $church) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                    <form method="post" action="{{ route('superadmin.churches.invite', $church) }}">
                                        @csrf
                                        <input type="hidden" name="invite_role" value="admin">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Generate Invite</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No churches yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $churches->links() }}
        </div>
    </main>
</body>
</html>

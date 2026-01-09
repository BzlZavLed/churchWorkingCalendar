<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Church</title>
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
        <div class="row justify-content-center">
            <div class="col-12 col-lg-7">
                <h1 class="h4 mb-3">Create Church</h1>
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="post" action="{{ route('superadmin.churches.store') }}" class="bg-white border rounded p-4">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">
                            Church Name
                            <input class="form-control" type="text" name="name" value="{{ old('name') }}" required>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Initial Invite Role
                            <select class="form-select" name="invite_role">
                                <option value="admin">admin</option>
                                <option value="manager">manager</option>
                                <option value="member">member</option>
                            </select>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Invite Email (optional)
                            <input class="form-control" type="email" name="invite_email" value="{{ old('invite_email') }}">
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Invite Max Uses
                            <input class="form-control" type="number" name="invite_max_uses" min="1" value="1">
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Invite Expires At (optional)
                            <input class="form-control" type="datetime-local" name="invite_expires_at" value="{{ old('invite_expires_at') }}">
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Create</button>
                </form>
                <p class="mt-3"><a href="{{ route('superadmin.churches.index') }}">Back</a></p>
            </div>
        </div>
    </main>
</body>
</html>

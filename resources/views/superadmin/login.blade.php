<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Superadmin Login</title>
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
            <div class="col-12 col-md-8 col-lg-5">
                <div class="bg-white border rounded p-4">
                    <h1 class="h4 mb-3">Superadmin Login</h1>
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="post" action="{{ route('superadmin.login.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">
                                Email
                                <input class="form-control" type="email" name="email" value="{{ old('email') }}" required>
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">
                                Password
                                <input class="form-control" type="password" name="password" required>
                            </label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

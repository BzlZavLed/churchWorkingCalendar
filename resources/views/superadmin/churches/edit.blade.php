<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Church</title>
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
                <h1 class="h4 mb-3">Edit Church</h1>
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="post" action="{{ route('superadmin.churches.update', $church) }}" class="bg-white border rounded p-4">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">
                            Church Name
                            <input class="form-control" type="text" name="name" value="{{ old('name', $church->name) }}" required>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save</button>
                </form>
                <p class="mt-3"><a href="{{ route('superadmin.churches.index') }}">Back</a></p>
            </div>
        </div>
    </main>
</body>
</html>

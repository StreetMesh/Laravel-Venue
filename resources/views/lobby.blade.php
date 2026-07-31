<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $identity->handle }}</title>
</head>
<body>
    <h1>A venue on StreetMesh</h1>
    <p>{{ $identity->did }}</p>

    <nav>
        <ul>
            @foreach ($navigation as $item)
                <li><a href="{{ route($item['route']) }}">{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </nav>
</body>
</html>

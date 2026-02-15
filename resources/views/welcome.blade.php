<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>AI Library Portal</title>
        @vite('resources/css/app.css')
    </head>
    <body className="antialiased">
        <div id="chat-root"></div>
        @viteReactRefresh
        @vite('resources/js/app.jsx')
    </body>
</html>

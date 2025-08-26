<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Request System - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Document Request System
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Login with your company NIK and password
            </p>
        </div>
        
        <form class="mt-8 space-y-6" method="POST" action="{{ route('login') }}">
            @csrf
            
            @if (session('login_success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('login_success.message') }}
                </div>
            @endif
            
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            <div>
                <label for="nik" class="sr-only">NIK</label>
                <input id="nik" name="nik" type="text" required 
                       class="relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                       placeholder="Enter your NIK" value="{{ old('nik') }}">
            </div>
            
            <div>
                <label for="password" class="sr-only">Password</label>
                <input id="password" name="password" type="password" required 
                       class="relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" 
                       placeholder="Enter your password">
            </div>
            
            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
            
            <div class="text-center text-sm text-gray-600">
                <p>Use your company NIK and HRIS password</p>
                <p class="mt-1">Legal team: Use <a href="/admin" class="text-indigo-600 hover:underline">/admin</a> panel</p>
                <p>Other users: Use <a href="/user" class="text-indigo-600 hover:underline">/user</a> panel</p>
            </div>
        </form>
    </div>
</body>
</html>
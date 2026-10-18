<x-app-layout>
    <div class="page-h"><div><h1>Profile</h1><p>Manage your account details and password.</p></div></div>

    <div style="max-width:640px;display:flex;flex-direction:column;gap:16px">
        <div class="card pad">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card pad">
            @include('profile.partials.update-password-form')
        </div>
        <div class="card pad">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>

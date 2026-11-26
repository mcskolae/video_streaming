<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Utilisateurs') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Nom') }}</th>
                            <th class="px-4 py-3">{{ __('Adresse e-mail') }}</th>
                            <th class="px-4 py-3">{{ __('Rôle') }}</th>
                            <th class="px-4 py-3">{{ __('État') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ $user->name }}
                                    @if ($user->id === $currentUserId)
                                        <span class="font-normal text-gray-500">({{ __('vous') }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->role->isAdministrator())
                                        <span class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-800">{{ __('Administrateur') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ __('Utilisateur') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($user->isSuspended())
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800">{{ __('Suspendu') }}</span>
                                    @else
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Actif') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.users.role', $user->id) }}" class="inline"
                                          data-confirm="{{ $user->role->isAdministrator() ? __('Retirer le rôle administrateur de :name ?', ['name' => $user->name]) : __('Donner le rôle administrateur à :name ?', ['name' => $user->name]) }}">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $user->role->isAdministrator() ? 'user' : 'admin' }}">
                                        <button type="submit" class="mr-3 text-sm font-medium text-indigo-700 hover:underline">
                                            {{ $user->role->isAdministrator() ? __('Retirer le rôle d\'administrateur') : __('Passer administrateur') }}
                                        </button>
                                    </form>
                                    @if ($user->isSuspended())
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-green-700 hover:underline">{{ __('Réactiver') }}</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.suspend', $user->id) }}" class="inline"
                                          data-confirm="{{ __('Suspendre :name ? Cette personne ne pourra plus se connecter.', ['name' => $user->name]) }}">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-red-700 hover:underline">{{ __('Suspendre') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="px-4 py-3">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>

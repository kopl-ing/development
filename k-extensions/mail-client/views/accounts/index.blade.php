@extends('kopling-mail-client::layouts.mail')

@section('content')
    <div class="max-w-3xl mx-auto flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ __('kopling-mail-client::messages.accounts') }}</h1>
            <x-k::modal id="account-create" label="{{ __('kopling-mail-client::messages.connect_mailbox') }}">
                <x-slot:trigger>{{ __('kopling-mail-client::messages.connect_mailbox') }}</x-slot:trigger>
                @if ($errors->any() && old('_form') === 'account-create')
                    <div class="alert alert-error mb-4">{{ $errors->first() }}</div>
                @endif
                {{--
                    hx-boost with no explicit hx-target swaps document.body on a successful
                    redirect (CLAUDE.md gotcha), which should close this dialog as a side effect
                    of the old node being replaced -- but relying on that implicitly, for a native
                    <dialog> opened via showModal(), is exactly the kind of swap-timing edge case
                    worth not trusting blindly. Closing it explicitly on a successful response is
                    the same fix regardless of the precise reason the implicit close wasn't
                    visually reliable.
                --}}
                <form method="POST" action="{{ route('kopling-mail-client::mail/accounts.store') }}" hx-boost="true"
                      hx-on:htmx:after:request="if (event.detail.successful) document.getElementById('account-create').close()"
                      class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="_form" value="account-create">
                    <x-k::form.input :data="['name' => 'label', 'label' => __('kopling-mail-client::messages.label'), 'description' => __('kopling-mail-client::messages.label_help'), 'value' => old('label')]" />
                    <x-k::form.input :data="['name' => 'email_address', 'label' => __('kopling-mail-client::messages.email_address'), 'type' => 'email', 'value' => old('email_address')]" />

                    <div class="divider my-0">{{ __('kopling-mail-client::messages.incoming_server') }}</div>
                    <x-k::form.input :data="['name' => 'incoming_host', 'label' => __('kopling-mail-client::messages.host'), 'value' => old('incoming_host')]" />
                    <x-k::form.input :data="['name' => 'incoming_port', 'label' => __('kopling-mail-client::messages.port'), 'type' => 'number', 'value' => old('incoming_port', 993)]" />
                    <x-k::form.select :data="['name' => 'incoming_encryption', 'label' => __('kopling-mail-client::messages.encryption'), 'options' => ['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => __('kopling-mail-client::messages.encryption_none')], 'value' => old('incoming_encryption', 'ssl')]" />

                    <div class="divider my-0">{{ __('kopling-mail-client::messages.outgoing_server') }}</div>
                    <x-k::form.input :data="['name' => 'outgoing_host', 'label' => __('kopling-mail-client::messages.host'), 'value' => old('outgoing_host')]" />
                    <x-k::form.input :data="['name' => 'outgoing_port', 'label' => __('kopling-mail-client::messages.port'), 'type' => 'number', 'value' => old('outgoing_port', 587)]" />
                    <x-k::form.select :data="['name' => 'outgoing_encryption', 'label' => __('kopling-mail-client::messages.encryption'), 'options' => ['ssl' => 'SSL/TLS', 'starttls' => 'STARTTLS', 'none' => __('kopling-mail-client::messages.encryption_none')], 'value' => old('outgoing_encryption', 'starttls')]" />

                    <div class="divider my-0">{{ __('kopling-mail-client::messages.credentials') }}</div>
                    <x-k::form.input :data="['name' => 'username', 'label' => __('kopling-mail-client::messages.username'), 'description' => __('kopling-mail-client::messages.username_help'), 'value' => old('username')]" />
                    <x-k::form.input :data="['name' => 'password', 'label' => __('kopling-mail-client::messages.password'), 'type' => 'password']" />
                    <x-k::form.toggle :data="['name' => 'is_default', 'label' => __('kopling-mail-client::messages.set_as_default'), 'value' => old('is_default')]" />

                    <button type="submit" class="btn btn-primary">{{ __('kopling-mail-client::messages.connect_mailbox') }}</button>
                </form>
            </x-k::modal>
        </div>

        @if ($accounts->isEmpty())
            <p class="opacity-60">{{ __('kopling-mail-client::messages.no_accounts') }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('kopling-mail-client::messages.label') }}</th>
                        <th>{{ __('kopling-mail-client::messages.email_address') }}</th>
                        <th>{{ __('kopling-mail-client::messages.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td>
                                {{ $account->label ?: '—' }}
                                @if ($account->is_default)
                                    <span class="badge badge-outline badge-sm">{{ __('kopling-mail-client::messages.default') }}</span>
                                @endif
                            </td>
                            <td>{{ $account->email_address }}</td>
                            <td>@include('kopling-mail-client::accounts.status')</td>
                            <td>
                                <form method="POST" action="{{ route('kopling-mail-client::mail/accounts.destroy', $account) }}"
                                      hx-boost="true" hx-confirm="{{ __('kopling-mail-client::messages.confirm_delete_account') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-mail-client::messages.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

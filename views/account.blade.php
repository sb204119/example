@extends('master.profile')

@section('profile-content')
    @include('includes.flash.error')
    @include('includes.flash.success')

    <h1 class="my-3">Профиль</h1>
    <div class="row">
        <div class="col-md-6">
            <label>Баланс: {{$user->balance}} - BTC</label>
        </div>
        <div class="col-md-6 text-right">
            <div class="btn-group" role="group" aria-label="">
                <a href="{{ route('profile.deposit') }}" class="btn btn-success">Пополнить</a>
            </div>
        </div>
    </div>
    <h3 class="mt-3">Логин</h3>
    <hr>
    <div class="card-columns">
        <label>{{$user->username}}</label>
    </div>
        <h3>Изменить аватар</h3>
        <hr>
        <form action="{{ route('profile.ava') }}" method="POST" enctype="multipart/form-data">
            {{ csrf_field() }}
            <div class="form-group">
                <input type="file" class="form-control border-0" name="file">
            </div>
            <div class="form-inline">
                <button type="submit" class="btn btn-primary mb-2">Сохранить</button>
            </div>
        </form>

        {{-- Ava --}}

        <h3 class="mt-3">Автарка</h3>
        <hr>
            <div class="card-columns">
             @if(!$user->avatar)
                    <img class="ava-img" src="{{ asset('storage/avatars/default.png') }}" alt="">
             @else
                    <img class="ava-img" src="{{ asset($user->avatar) }}" alt="">
             @endif
            </div>
@stop
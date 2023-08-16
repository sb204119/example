@extends('master.profile')

@section('profile-content')
    @include('includes.flash.error')
    @include('includes.flash.success')
    <div class="col-md-9 page-content">
        <div class="inner-box">
            <h2 class="title-2"><i class="fas fa-coins"></i> Пополнение </h2>

            <div style="clear:both"></div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {

                    const deadline = new Date('{{$time}}');
                    deadline.setDate(deadline.getDate() + 1);

                    let timerId = null;

                    function declensionNum(num, words) {
                        return words[(num % 100 > 4 && num % 100 < 20) ? 2 : [2, 0, 1, 1, 1, 2][(num % 10 < 5) ? num % 10 : 5]];
                    }

                    function countdownTimer() {
                        const diff = deadline - new Date();
                        if (diff <= 0) {
                            clearInterval(timerId);
                        }
                        const hours = diff > 0 ? Math.floor(diff / 1000 / 60 / 60) % 24 : 0;
                        const minutes = diff > 0 ? Math.floor(diff / 1000 / 60) % 60 : 0;
                        const seconds = diff > 0 ? Math.floor(diff / 1000) % 60 : 0;
                        $hours.textContent = hours < 10 ? '0' + hours : hours;
                        $minutes.textContent = minutes < 10 ? '0' + minutes : minutes;
                        $seconds.textContent = seconds < 10 ? '0' + seconds : seconds;
                        $hours.dataset.title = declensionNum(hours, ['час', 'часа', 'часов']);
                        $minutes.dataset.title = declensionNum(minutes, ['минута', 'минуты', 'минут']);
                        $seconds.dataset.title = declensionNum(seconds, ['секунда', 'секунды', 'секунд']);
                    }

                    const $hours = document.querySelector('.timer__hours');
                    const $minutes = document.querySelector('.timer__minutes');
                    const $seconds = document.querySelector('.timer__seconds');

                    countdownTimer();

                    timerId = setInterval(countdownTimer, 1000);
                });
            </script>
            Время для пополнения
            <div class="timer">
                <div class="timer__items">
                    <div class="timer__item timer__hours">00</div>&nbsp;:&nbsp;
                    <div class="timer__item timer__minutes">00</div>&nbsp;:&nbsp;
                    <div class="timer__item timer__seconds">00</div>
                </div>
            </div>
            <div>
                <br>
                <strong>
                    Пополнение

                    <div class="col-md-6">
                        <input name="name" type="text" class="form-control" placeholder="" id="myvalue" value="{{ $deposits }}" readonly>

                        <br>
                        <div class="list-group">
                            <div class="alert alert-info sl" role="alert">
                                Средства зачисляются в течение 3-х минут с третьего подтверждения в сети.
                                Один адрес принимает только один платёж.
                                Если вам надо пополнить ещё, дождитесь обработки первого зачисления и получите новый кошелек.
                            </div>
                        </div>
                </strong>
            </div>

            <script>
                function copyToClipboard() {
                    var textBox = document.getElementById("myvalue");
                    textBox.select();
                    document.execCommand("copy");
                    alert('Скопировано');
                }
            </script>

            <button class="btn btn-block btn-border btn-success" style="float: left; width: 24%; margin-top: 1%;" onclick="copyToClipboard()"> Копировать</button>

            <a class="btn btn-block btn-border btn-danger"  style="float: left; width: 24%; margin-top: 1%; margin-left: 2%;" href="{{ route('profile.deposit_del') }}">
                <i class="fab fa-stop"></i> Отмена
            </a>

        </div>
    </div>
        
@stop
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('push:process --limit=100')->everyMinute()->withoutOverlapping();

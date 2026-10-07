<?php

use App\Http\Controllers\Api\QueueMonitorController;

Route::get('/queue-monitor', [QueueMonitorController::class, 'status']);
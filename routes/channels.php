<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('report-generation.{sessionId}', function ($user, $sessionId) {
    // Only allow the authenticated user to listen to their own report generation channel
    // The sessionId should be unique per user session and we can validate it belongs to the user
    return $sessionId === session()->getId();
});

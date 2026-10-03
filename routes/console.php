<?php

// Equipo 7: expira reservas vencidas cada 5 min
Illuminate\Support\Facades\Schedule::command('e7:expire')->everyFiveMinutes();
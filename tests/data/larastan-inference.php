<?php

app('translator')->get('this inference requires larastan to work');
app(\Illuminate\Contracts\Translation\Translator::class)->get('this inference requires larastan to work');
resolve(\Illuminate\Contracts\Translation\Translator::class)->get('this inference requires larastan to work');

/** @var \Illuminate\Contracts\Foundation\Application $app */
// $app->get('translator')->get('this inference requires larastan to work'); this one is not working for some reason
$app->make('translator')->get('this inference requires larastan to work');

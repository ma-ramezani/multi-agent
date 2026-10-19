<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\RagEnvironmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::resource('agents', AgentController::class)->except(['show']);
Route::resource('rags', RagEnvironmentController::class)->parameters(['rags' => 'rag']);
Route::post('rags/{rag}/upload', [RagEnvironmentController::class, 'upload'])->name('rags.upload');
Route::get('rags/{rag}/docs/{document}/edit', [RagEnvironmentController::class, 'editDoc'])->name('rags.docs.edit');
Route::put('rags/{rag}/docs/{document}', [RagEnvironmentController::class, 'updateDoc'])->name('rags.docs.update');
Route::post('rags/{rag}/docs/{document}/reindex', [RagEnvironmentController::class, 'reindex'])->name('rags.reindex');
Route::delete('rags/{rag}/docs/{document}', [RagEnvironmentController::class, 'deleteDoc'])->name('rags.docs.destroy');

Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
Route::post('agents/{agent}/chats/start', [ChatController::class, 'start'])->name('chats.start');
Route::get('chats/{session}', [ChatController::class, 'show'])->name('chats.show');
Route::post('chats/{session}/send', [ChatController::class, 'send'])->middleware('throttle:chat')->name('chats.send');
Route::get('chats/{session}/stream', [ChatController::class, 'stream'])->middleware('throttle:chat')->name('chats.stream');
Route::post('chats/{session}/upload', [ChatController::class, 'upload'])->middleware('throttle:chat')->name('chats.upload');
Route::delete('chats/{session}', [ChatController::class, 'destroy'])->name('chats.destroy');

Route::get('tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
Route::post('tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
Route::delete('tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');

Route::get('api-docs', fn () => view('api-docs'))->name('api.docs');

Route::get('security', [SecurityController::class, 'index'])->name('security.index');
Route::post('security', [SecurityController::class, 'update'])->name('security.update');
Route::delete('security/events', [SecurityController::class, 'clearEvents'])->name('security.clear');

Route::get('files/chat/{message}/{index}', [FileController::class, 'chatAttachment'])->name('files.chat')->whereNumber('index');
Route::get('files/rag/{document}', [FileController::class, 'ragDocument'])->name('files.rag');

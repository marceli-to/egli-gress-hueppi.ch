<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profil') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column: Account -->
                <div class="space-y-6">
                    <!-- Account Section -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="font-semibold text-gray-800">Mein Konto</h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xl font-medium text-gray-900">{{ auth()->user()->display_name ?? auth()->user()->name }}</p>
                                    <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
                                </div>
                                <button wire:click="logout" class="px-4 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                                    Abmelden
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Points System / Rules -->
                <div class="space-y-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="font-semibold text-gray-800">Punktesystem / Regeln</h3>
                        </div>
                        <div class="p-4 space-y-6">
                            <!-- Group Phase -->
                            <div>
                                <h4 class="font-medium text-gray-800 mb-3">Gruppenphase (max. 10 Punkte pro Tipp)</h4>
                                <table class="w-full text-sm">
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtiger Sieger / Tendenz</td>
                                        <td class="py-1 text-right font-medium">5 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tordifferenz</td>
                                        <td class="py-1 text-right font-medium">3 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tore Heimmannschaft</td>
                                        <td class="py-1 text-right font-medium">1 Punkt</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tore Gastmannschaft</td>
                                        <td class="py-1 text-right font-medium">1 Punkt</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- K.O. Phase -->
                            <div>
                                <h4 class="font-medium text-gray-800 mb-3">K.O.-Phase (max. 20 Punkte pro Tipp)</h4>
                                <table class="w-full text-sm">
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtiger Sieger</td>
                                        <td class="py-1 text-right font-medium">10 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tendenz nach 90/120 Min</td>
                                        <td class="py-1 text-right font-medium">3 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tordifferenz</td>
                                        <td class="py-1 text-right font-medium">3 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tore Heimmannschaft</td>
                                        <td class="py-1 text-right font-medium">2 Punkte</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 text-gray-600">Richtige Tore Gastmannschaft</td>
                                        <td class="py-1 text-right font-medium">2 Punkte</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Tip Submission -->
                            <div>
                                <h4 class="font-medium text-gray-800 mb-3">Tippabgabe</h4>
                                <ul class="text-sm text-gray-600 space-y-2">
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>Spieltipps konnen bis zum Anpfiff des jeweiligen Spiels abgegeben werden.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>Bonustipps werden bei Anpfiff des Eroffnungsspiels gesperrt.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>Bei K.O.-Spielen kann auf Unentschieden getippt werden - dann muss der Sieger im Elfmeterschiessen gewahlt werden.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

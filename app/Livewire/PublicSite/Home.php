<?php

namespace App\Livewire\PublicSite;

use App\Queries\PublicSite\HomepageData;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Home extends Component
{
    private const MENU_CATEGORIES = [
        [
            'id' => 'dimsum-kukus',
            'title' => 'Dimsum',
            'items' => [
                [
                    'name' => 'Dimsum Ori',
                    'variants' => [
                        [
                            'label' => '4 pcs',
                            'price' => 16000,
                        ],
                        [
                            'label' => '6 pcs',
                            'price' => 24000,
                            'recommended' => true,
                        ],
                        [
                            'label' => '8 pcs',
                            'price' => 30000,
                        ],
                    ],
                ],
                [
                    'name' => 'Dimsum Mentai',
                    'variants' => [
                        [
                            'label' => '4 pcs',
                            'price' => 21000,
                        ],
                        [
                            'label' => '6 pcs',
                            'price' => 28000,
                            'recommended' => true,
                        ],
                        [
                            'label' => '8 pcs',
                            'price' => 38000,
                        ],
                    ],
                ],
                [
                    'name' => 'Dimsum Cheesemelt',
                    'variants' => [
                        [
                            'label' => '4 pcs',
                            'price' => 25000,
                        ],
                        [
                            'label' => '6 pcs',
                            'price' => 35000,
                        ],
                        [
                            'label' => '8 pcs',
                            'price' => 48000,
                        ],
                    ],
                ],
                [
                    'name' => 'Dimsum Mix',
                    'variants' => [
                        [
                            'label' => '6 pcs',
                            'price' => 30000,
                        ],
                        [
                            'label' => '8 pcs',
                            'price' => 45000,
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 'lumpia',
            'title' => 'Lumpia',
            'items' => [
                [
                    'name' => 'Lumpia Original',
                    'variants' => [
                        [
                            'label' => '3 pcs',
                            'price' => 24000,
                        ],
                        [
                            'label' => '5 pcs',
                            'price' => 36000,
                        ],
                    ],
                ],
                [
                    'name' => 'Lumpia Mentai',
                    'variants' => [
                        [
                            'label' => '3 pcs',
                            'price' => 28000,
                        ],
                        [
                            'label' => '5 pcs',
                            'price' => 40000,
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 'chiquro',
            'title' => 'Chiquro',
            'items' => [
                [
                    'name' => 'Chiquro Garlic',
                    'variants' => [
                        [
                            'label' => 'Per porsi',
                            'price' => 26000,
                        ],
                    ],
                ],
                [
                    'name' => 'Chiquro Lava',
                    'variants' => [
                        [
                            'label' => 'Per porsi',
                            'price' => 26000,
                        ],
                    ],
                ],
                [
                    'name' => 'Chiquro Mentai',
                    'variants' => [
                        [
                            'label' => 'Per porsi',
                            'price' => 26000,
                            'recommended' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'Chiquro Cheese',
                    'variants' => [
                        [
                            'label' => 'Per porsi',
                            'price' => 28000,
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 'paket-besar',
            'title' => 'Paket Besar',
            'items' => [
                [
                    'name' => 'Paket Besar Dimsum Mix',
                    'variants' => [
                        [
                            'label' => 'Paket',
                            'price' => 90000,
                        ],
                    ],
                ],
                [
                    'name' => 'Paket Besar Dimsum Mentai',
                    'variants' => [
                        [
                            'label' => 'Paket',
                            'price' => 85000,
                        ],
                    ],
                ],
                [
                    'name' => 'Paket Besar Dimsum Cheesemelt',
                    'variants' => [
                        [
                            'label' => 'Paket',
                            'price' => 100000,
                        ],
                    ],
                ],
            ],
        ],
    ];

    private const FALLBACK_BRANCHES = [
        [
            'name' => 'Cabang 1 · Galaxy',
            'address' => 'Di depan SPBU BP, Galaxy',
            'hours' => [
                [
                    'days' => 'Setiap hari',
                    'time' => '14.00–00.00',
                ],
            ],
            'map' => 'https://maps.app.goo.gl/u9Gg5ufsxPXNCmwd9',
        ],
        [
            'name' => 'Cabang 2 · Galaxy',
            'address' => 'Di samping RS Hermina, Galaxy',
            'hours' => [
                [
                    'days' => 'Setiap hari',
                    'time' => '08.00–18.00',
                ],
            ],
            'map' => 'https://maps.app.goo.gl/xyGKHdRHhcAeBMkA7',
        ],
        [
            'name' => 'Cabang 3 · Pekayon Jaya',
            'address' => 'Pekayon Jaya',
            'hours' => [
                [
                    'days' => 'Senin–Sabtu',
                    'time' => '14.00–22.00',
                ],
                [
                    'days' => 'Minggu',
                    'time' => '06.00–16.00',
                ],
            ],
            'map' => 'https://maps.app.goo.gl/vBkAyD4WVhMoQUvm6',
        ],
        [
            'name' => 'Cabang 4 · Jalan Raya Pekayon',
            'address' => 'Di samping Pakuwon Mall Bekasi',
            'hours' => [
                [
                    'days' => 'Setiap hari',
                    'time' => '14.30–22.15',
                ],
            ],
            'map' => 'https://maps.app.goo.gl/6efBgdCTcRjrqytH7',
        ],
    ];

    public function render(): View
    {
        $data = app(HomepageData::class)->get();

        return view('livewire.public-site.home', [
            ...$data,
            'menuCategories' => self::MENU_CATEGORIES,
            'displayBranches' => $data['outlets']->isNotEmpty() ? $data['outlets'] : self::FALLBACK_BRANCHES,
            'whatsappNumber' => '62895343256336',
            'gofoodUrl' => 'https://gofood.co.id/',
        ])->layout('layouts.application', [
            'title' => ($data['business']['name'] ?: 'Dimsum').' — Dimsum Hangat, Dibuat dengan Hati',
            'description' => ($data['business']['name'] ?: 'Dimsum').' menyajikan dimsum hangat dan lezat untuk menemani waktu makan Anda.',
        ]);
    }
}

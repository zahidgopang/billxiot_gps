<!-- resources/views/frontend/pages/press.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.press.title'))
@section('description', 'Latest news, press releases, and media resources about FalconEyeGPS.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-purple-50 to-pink-100 dark:from-slate-950 dark:via-purple-950 dark:to-pink-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-full text-purple-600 dark:text-purple-400 font-semibold text-sm mb-4">
            MEDIA CENTER
        </span>
            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                Press & <span class="bg-gradient-to-r from-purple-500 to-pink-600 bg-clip-text text-transparent">Media</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                Latest news, press releases, and media resources about FalconEyeGPS.
            </p>
        </div>
    </section>

    <!-- Press Releases -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Latest Press Releases</h2>
                <p class="text-slate-600 dark:text-slate-400">Official announcements and company news</p>
            </div>

            <div class="space-y-8 max-w-4xl mx-auto">
                @php
                    $releases = [
                        [
                            'date' => 'Dec 15, 2024',
                            'title' => 'FalconEyeGPS Releases Updated Android Fleet App',
                            'excerpt' => 'New feature predicts vehicle maintenance needs up to 30 days in advance, reducing downtime by 40%.',
                            'category' => 'Product Launch'
                        ],
                        [
                            'date' => 'Nov 22, 2024',
                            'title' => 'FalconEyeGPS Partners with Major Logistics Company',
                            'excerpt' => 'Strategic partnership to deploy GPS tracking across 5,000-vehicle fleet.',
                            'category' => 'Partnership'
                        ],
                        [
                            'date' => 'Oct 10, 2024',
                            'title' => 'Company Reaches 10,000 Client Milestone',
                            'excerpt' => 'FalconEyeGPS now serves over 10,000 businesses worldwide across 150+ countries.',
                            'category' => 'Milestone'
                        ],
                        [
                            'date' => 'Sep 5, 2024',
                            'title' => 'Sustainability Report 2024 Released',
                            'excerpt' => 'Annual report shows clients reduced carbon emissions by 2.3 million tons through route optimization.',
                            'category' => 'Sustainability'
                        ],
                    ];
                @endphp

                @foreach($releases as $release)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
                        <div class="flex items-start gap-4">
                            <div class="text-center flex-shrink-0">
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ date('M', strtotime($release['date'])) }}</div>
                                <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ date('d', strtotime($release['date'])) }}</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">{{ date('Y', strtotime($release['date'])) }}</div>
                            </div>
                            <div class="flex-grow">
                            <span class="inline-block px-3 py-1 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 text-xs font-medium mb-2">
                                {{ $release['category'] }}
                            </span>
                                <h3 class="font-bold text-xl mb-2">{{ $release['title'] }}</h3>
                                <p class="text-slate-600 dark:text-slate-400 mb-4">{{ $release['excerpt'] }}</p>
                                <div class="flex items-center gap-4">
                                    <a href="#" class="text-purple-600 dark:text-purple-400 font-medium hover:underline">
                                        Read Full Release →
                                    </a>
                                    <a href="#" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300">
                                        <i class="fa-solid fa-download"></i> PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-12">
                <a href="#" class="inline-flex items-center gap-2 px-6 py-3 border-2 border-slate-300 dark:border-slate-700 rounded-xl font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    View All Press Releases
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- In The News -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">In The News</h2>
                <p class="text-slate-600 dark:text-slate-400">Featured in leading publications</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $news = [
                        [
                            'publication' => 'TechCrunch',
                            'title' => 'How AI is Revolutionizing Fleet Management',
                            'excerpt' => 'FalconEyeGPS leads the way with predictive analytics.',
                            'logo' => 'fa-solid fa-newspaper',
                            'color' => 'from-orange-500 to-amber-600'
                        ],
                        [
                            'publication' => 'Forbes',
                            'title' => 'The Future of Logistics Technology',
                            'excerpt' => 'Interview with FalconEyeGPS CEO on industry trends.',
                            'logo' => 'fa-solid fa-chart-line',
                            'color' => 'from-blue-500 to-indigo-600'
                        ],
                        [
                            'publication' => 'Bloomberg',
                            'title' => 'Sustainability Through Technology',
                            'excerpt' => 'How GPS optimization reduces carbon footprint.',
                            'logo' => 'fa-solid fa-leaf',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                    ];
                @endphp

                @foreach($news as $item)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $item['color'] }} flex items-center justify-center mb-6">
                            <i class="{{ $item['logo'] }} text-white text-xl"></i>
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">{{ $item['publication'] }}</div>
                        <h3 class="font-bold text-lg mb-3">{{ $item['title'] }}</h3>
                        <p class="text-slate-600 dark:text-slate-400 text-sm mb-4">{{ $item['excerpt'] }}</p>
                        <a href="#" class="text-slate-700 dark:text-slate-300 font-medium hover:underline">
                            Read Article →
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Media Kit -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold mb-4">Media Resources</h2>
                    <p class="text-slate-600 dark:text-slate-400">Download logos, brand assets, and press materials</p>
                </div>

                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <a href="#" class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-download text-white"></i>
                            </div>
                            <div>
                                <div class="font-medium">Logo Pack</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">PNG, SVG, EPS formats</div>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="p-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                                <i class="fa-solid fa-images text-white"></i>
                            </div>
                            <div>
                                <div class="font-medium">Brand Assets</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">Photos, screenshots, videos</div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="text-center">
                    <a href="{{ route('contact') }}?subject=Media Inquiry"
                       class="inline-flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-xl hover:shadow-xl transition-all">
                        <i class="fa-solid fa-envelope"></i>
                        Contact Press Team
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Press Contact -->
    <section class="py-20 bg-gradient-to-r from-slate-900 to-slate-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Press Contact</h2>
            <p class="text-xl text-slate-300 mb-8">For media inquiries, please contact our press team</p>

            <div class="grid md:grid-cols-2 gap-8 mb-8">
                <div class="p-6 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20">
                    <h3 class="font-bold text-white mb-2">Jessica Parker</h3>
                    <div class="text-slate-300 mb-3">Head of Communications</div>
                    <div class="text-white">
                        <i class="fa-solid fa-envelope mr-2"></i>
                        press@falconeyegps.com
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20">
                    <h3 class="font-bold text-white mb-2">General Inquiries</h3>
                    <div class="text-slate-300 mb-3">Media Relations Team</div>
                    <div class="text-white">
                        <i class="fa-solid fa-phone mr-2"></i>
                        +1 (555) 123-4567
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

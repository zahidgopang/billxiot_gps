<!-- resources/views/frontend/pages/careers.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.careers.title'))
@section('description', 'Explore career opportunities at FalconEyeGPS. Join our team of innovators in GPS tracking technology.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-amber-50 to-orange-100 dark:from-slate-950 dark:via-amber-950 dark:to-orange-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-amber-500/10 to-orange-500/10 rounded-full text-amber-600 dark:text-amber-400 font-semibold text-sm mb-4">
            JOIN OUR TEAM
        </span>
            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                Build the Future of <span class="bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent">Fleet Technology</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto mb-8">
                Join a team of passionate innovators transforming how businesses manage their fleets worldwide.
            </p>
            <a href="#openings" class="inline-flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-white font-semibold rounded-xl hover:shadow-xl transition-all">
                View Open Positions
                <i class="fa-solid fa-arrow-down"></i>
            </a>
        </div>
    </section>

    <!-- Why Join Us -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Why Join FalconEyeGPS?</h2>
                <p class="text-slate-600 dark:text-slate-400">We offer more than just a job</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-rocket text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-4">Growth Opportunities</h3>
                    <ul class="space-y-3 text-slate-600 dark:text-slate-400">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Professional development budget</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Mentorship programs</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Leadership training</span>
                        </li>
                    </ul>
                </div>

                <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-heart text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-4">Great Benefits</h3>
                    <ul class="space-y-3 text-slate-600 dark:text-slate-400">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Comprehensive health insurance</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Flexible remote work options</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Generous vacation policy</span>
                        </li>
                    </ul>
                </div>

                <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-users text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-4">Awesome Culture</h3>
                    <ul class="space-y-3 text-slate-600 dark:text-slate-400">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Collaborative environment</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Regular team events</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                            <span>Diverse & inclusive workplace</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Job Openings -->
    <section id="openings" class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">Open Positions</h2>
                <p class="text-slate-600 dark:text-slate-400">Join us in building the future</p>
            </div>

            <div class="space-y-6 max-w-4xl mx-auto">
                @php
                    $jobs = [
                        [
                            'title' => 'Senior Backend Engineer',
                            'department' => 'Engineering',
                            'location' => 'Remote',
                            'type' => 'Full-time',
                            'color' => 'from-sky-500 to-blue-600'
                        ],
                        [
                            'title' => 'Frontend Developer',
                            'department' => 'Engineering',
                            'location' => 'San Francisco',
                            'type' => 'Full-time',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                        [
                            'title' => 'Product Manager',
                            'department' => 'Product',
                            'location' => 'Remote',
                            'type' => 'Full-time',
                            'color' => 'from-amber-500 to-orange-600'
                        ],
                        [
                            'title' => 'Sales Executive',
                            'department' => 'Sales',
                            'location' => 'New York',
                            'type' => 'Full-time',
                            'color' => 'from-purple-500 to-pink-600'
                        ],
                        [
                            'title' => 'Customer Success Manager',
                            'department' => 'Support',
                            'location' => 'Remote',
                            'type' => 'Full-time',
                            'color' => 'from-red-500 to-rose-600'
                        ],
                    ];
                @endphp

                @foreach($jobs as $job)
                    <div class="p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-xl mb-2">{{ $job['title'] }}</h3>
                                <div class="flex flex-wrap gap-3">
                                <span class="px-3 py-1 rounded-full bg-gradient-to-r {{ $job['color'] }} text-white text-xs font-medium">
                                    {{ $job['department'] }}
                                </span>
                                    <span class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs">
                                    <i class="fa-solid fa-location-dot mr-1"></i>
                                    {{ $job['location'] }}
                                </span>
                                    <span class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs">
                                    <i class="fa-solid fa-clock mr-1"></i>
                                    {{ $job['type'] }}
                                </span>
                                </div>
                            </div>
                            <a href="{{ route('contact') }}?subject=Job Application: {{ $job['title'] }}"
                               class="px-6 py-2 border-2 border-slate-300 dark:border-slate-700 rounded-xl font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors whitespace-nowrap">
                                Apply Now
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-12">
                <p class="text-slate-600 dark:text-slate-400 mb-4">Don't see the perfect role?</p>
                <a href="{{ route('contact') }}?subject=General Career Inquiry"
                   class="inline-flex items-center gap-2 px-6 py-3 border-2 border-slate-300 dark:border-slate-700 rounded-xl font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i class="fa-solid fa-envelope"></i>
                    Send General Application
                </a>
            </div>
        </div>
    </section>

    <!-- Internship Program -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-graduation-cap text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold mb-4">Internship Program</h2>
                <p class="text-xl text-slate-600 dark:text-slate-400 mb-8">
                    Looking to kickstart your career in tech? Our internship program offers hands-on experience with cutting-edge GPS technology.
                </p>
                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">12+</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">Weeks Program</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">Paid</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">Competitive Stipend</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mb-2">Mentorship</div>
                        <div class="text-sm text-slate-600 dark:text-slate-400">1:1 Guidance</div>
                    </div>
                </div>
                <a href="{{ route('contact') }}?subject=Internship Inquiry"
                   class="inline-flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-semibold rounded-xl hover:shadow-xl transition-all">
                    <i class="fa-solid fa-briefcase"></i>
                    Apply for Internship
                </a>
            </div>
        </div>
    </section>

@endsection

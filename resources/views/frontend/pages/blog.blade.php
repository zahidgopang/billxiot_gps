<!-- resources/views/frontend/pages/blog.blade.php -->
@extends('frontend.layout')

@section('title', __('frontend.pages.blog.title'))
@section('description', 'Latest insights, articles, and news about GPS tracking and fleet management technology.')

@section('content')

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-indigo-50 to-violet-100 dark:from-slate-950 dark:via-indigo-950 dark:to-violet-950"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <span class="inline-block px-4 py-1.5 bg-gradient-to-r from-indigo-500/10 to-violet-500/10 rounded-full text-indigo-600 dark:text-indigo-400 font-semibold text-sm mb-4">
            INSIGHTS & NEWS
        </span>
            <h1 class="text-5xl lg:text-6xl font-bold mb-6">
                FalconEyeGPS <span class="bg-gradient-to-r from-indigo-500 to-violet-600 bg-clip-text text-transparent">Blog</span>
            </h1>
            <p class="text-xl text-slate-600 dark:text-slate-300 max-w-3xl mx-auto">
                Expert insights, industry trends, and the latest in GPS tracking technology.
            </p>
        </div>
    </section>

    <!-- Featured Post -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-4">Featured Article</h2>
                <p class="text-slate-600 dark:text-slate-400">Latest from our team</p>
            </div>

            <div class="rounded-2xl overflow-hidden glass border border-slate-200 dark:border-slate-800">
                <div class="md:flex">
                    <div class="md:w-1/2 bg-gradient-to-br from-indigo-500 to-violet-600 p-12 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-white text-sm font-medium mb-2">FEATURED</div>
                            <div class="text-4xl font-bold text-white mb-4">AI in Fleet Management</div>
                            <div class="text-indigo-200">The Future of Predictive Analytics</div>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-12">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center text-white font-bold">
                                AJ
                            </div>
                            <div>
                                <div class="font-bold">Alex Johnson</div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">CEO & Founder • Dec 15, 2024</div>
                            </div>
                        </div>

                        <p class="text-slate-600 dark:text-slate-400 mb-6">
                            Artificial Intelligence is transforming how businesses manage their fleets. From predictive maintenance to route optimization, learn how AI can reduce costs by up to 30% while improving safety and efficiency.
                        </p>

                        <div class="flex flex-wrap gap-2 mb-8">
                        <span class="px-3 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-medium">
                            AI Technology
                        </span>
                            <span class="px-3 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-medium">
                            Fleet Management
                        </span>
                            <span class="px-3 py-1 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-medium">
                            Innovation
                        </span>
                        </div>

                        <a href="#" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-semibold rounded-xl hover:shadow-xl transition-all">
                            Read Full Article
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Posts -->
    <section class="py-20 bg-slate-50 dark:bg-slate-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-4">Latest Articles</h2>
                <p class="text-slate-600 dark:text-slate-400">Industry insights and updates</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @php
                    $posts = [
                        [
                            'title' => '5 Ways GPS Tracking Reduces Fuel Costs',
                            'excerpt' => 'Practical strategies to optimize fuel consumption using real-time tracking data.',
                            'author' => 'Maria Chen',
                            'date' => 'Nov 28, 2024',
                            'read_time' => '5 min read',
                            'category' => 'Cost Optimization',
                            'color' => 'from-emerald-500 to-teal-600'
                        ],
                        [
                            'title' => 'The Future of Electric Fleet Management',
                            'excerpt' => 'How GPS technology enables efficient management of electric vehicle fleets.',
                            'author' => 'David Wilson',
                            'date' => 'Nov 15, 2024',
                            'read_time' => '7 min read',
                            'category' => 'Sustainability',
                            'color' => 'from-sky-500 to-blue-600'
                        ],
                        [
                            'title' => 'Compliance & Regulations in 2024',
                            'excerpt' => 'Latest regulatory requirements for fleet operators and how to stay compliant.',
                            'author' => 'Sarah Miller',
                            'date' => 'Oct 30, 2024',
                            'read_time' => '6 min read',
                            'category' => 'Compliance',
                            'color' => 'from-amber-500 to-orange-600'
                        ],
                        [
                            'title' => 'Route Optimization Algorithms Explained',
                            'excerpt' => 'Deep dive into the algorithms that power efficient route planning.',
                            'author' => 'Tech Team',
                            'date' => 'Oct 18, 2024',
                            'read_time' => '8 min read',
                            'category' => 'Technology',
                            'color' => 'from-purple-500 to-pink-600'
                        ],
                        [
                            'title' => 'Driver Safety Features Every Fleet Needs',
                            'excerpt' => 'Essential safety features to protect drivers and reduce accidents.',
                            'author' => 'Safety Team',
                            'date' => 'Oct 5, 2024',
                            'read_time' => '4 min read',
                            'category' => 'Safety',
                            'color' => 'from-red-500 to-rose-600'
                        ],
                        [
                            'title' => 'Integration with Logistics Software',
                            'excerpt' => 'How to integrate GPS tracking with existing logistics and ERP systems.',
                            'author' => 'Integration Team',
                            'date' => 'Sep 22, 2024',
                            'read_time' => '6 min read',
                            'category' => 'Integration',
                            'color' => 'from-indigo-500 to-violet-600'
                        ],
                    ];
                @endphp

                @foreach($posts as $post)
                    <div class="group">
                        <div class="h-full p-6 rounded-2xl glass border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                            <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 rounded-full bg-gradient-to-r {{ $post['color'] }} text-white text-xs font-medium">
                                {{ $post['category'] }}
                            </span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $post['read_time'] }}</span>
                            </div>

                            <h3 class="font-bold text-xl mb-3 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                {{ $post['title'] }}
                            </h3>

                            <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">{{ $post['excerpt'] }}</p>

                            <div class="flex items-center justify-between pt-6 border-t border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br {{ $post['color'] }} flex items-center justify-center text-white text-xs font-bold">
                                        {{ substr($post['author'], 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-sm">{{ $post['author'] }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $post['date'] }}</div>
                                    </div>
                                </div>

                                <a href="#" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-12">
                <a href="#" class="inline-flex items-center gap-2 px-8 py-3 border-2 border-slate-300 dark:border-slate-700 rounded-xl font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    View All Articles
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="p-8 rounded-2xl glass border border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-envelope-open-text text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold mb-4">Stay Updated</h2>
                <p class="text-xl text-slate-600 dark:text-slate-400 mb-8">
                    Subscribe to our newsletter for the latest insights and updates.
                </p>

                <form class="max-w-md mx-auto">
                    <div class="flex gap-3">
                        <input type="email"
                               placeholder="Your email address"
                               class="flex-grow px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 text-white font-semibold rounded-xl hover:shadow-xl transition-all">
                            Subscribe
                        </button>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-3">
                        No spam. Unsubscribe anytime.
                    </p>
                </form>
            </div>
        </div>
    </section>

@endsection

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/brand/favicon-192.png" type="image/png" sizes="192x192">
        <link rel="icon" href="/brand/favicon-512.png" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="/brand/favicon-192.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        @php
            $bfSeoComponent = (string) ($page['component'] ?? '');
            $bfSeoProps = is_array($page['props'] ?? null)
                ? $page['props']
                : [];
            $bfSeoAppName = (string) config(
                'app.name',
                'BusinessFinder Nigeria'
            );

            $bfSeoPageTitle = $bfSeoAppName;
            $bfSeoDescription = null;
            $bfSeoRobots = null;
            $bfSeoCanonical = null;
            $bfSeoOgTitle = null;
            $bfSeoOgType = 'website';
            $bfSeoPrev = null;
            $bfSeoNext = null;

            if ($bfSeoComponent === 'Welcome') {
                $bfSeoBaseTitle =
                    'AI-Powered Business Finder for Nigeria';
                $bfSeoPageTitle =
                    $bfSeoBaseTitle . ' - ' . $bfSeoAppName;
                $bfSeoDescription =
                    'Discover businesses, services and local providers across Nigeria through intelligent search, trusted business information and real Nigerian geography.';
                $bfSeoRobots = 'index,follow';
                $bfSeoCanonical = url('/');
                $bfSeoOgTitle = $bfSeoBaseTitle;
            } elseif ($bfSeoComponent === 'Categories') {
                $bfSeoBaseTitle = 'Explore Business Categories';
                $bfSeoPageTitle =
                    $bfSeoBaseTitle . ' - ' . $bfSeoAppName;
                $bfSeoDescription =
                    'Browse BusinessFinder Nigeria categories and discover services across Nigeria.';
                $bfSeoRobots = 'index,follow';
                $bfSeoCanonical = url('/categories');
                $bfSeoOgTitle = $bfSeoBaseTitle;
            } elseif ($bfSeoComponent === 'Search') {
                $bfSeoBaseTitle = 'Find Nigerian Businesses';
                $bfSeoPageTitle =
                    $bfSeoBaseTitle . ' - ' . $bfSeoAppName;
                $bfSeoDescription =
                    'Search real Nigerian businesses and services by name, category and location.';
                $bfSeoRobots = 'noindex,follow';
                $bfSeoCanonical = url('/search');
                $bfSeoOgTitle =
                    'Find Nigerian Businesses | BusinessFinder Nigeria';
            } elseif ($bfSeoComponent === 'BusinessShow') {
                $bfBusinessName = trim(
                    (string) data_get(
                        $bfSeoProps,
                        'business.name',
                        'Business'
                    )
                );
                $bfBusinessDescription = trim(
                    (string) (
                        data_get(
                            $bfSeoProps,
                            'business.shortDescription'
                        )
                        ?: data_get(
                            $bfSeoProps,
                            'business.description'
                        )
                        ?: "Find {$bfBusinessName} on BusinessFinder Nigeria."
                    )
                );

                $bfSeoPageTitle =
                    $bfBusinessName . ' - ' . $bfSeoAppName;
                $bfSeoDescription = $bfBusinessDescription;
                $bfSeoRobots = 'index,follow';
                $bfSeoCanonical = trim(
                    (string) data_get(
                        $bfSeoProps,
                        'canonicalUrl',
                        ''
                    )
                );
                $bfSeoOgTitle = $bfBusinessName;
                $bfSeoOgType = 'business.business';
            } elseif ($bfSeoComponent === 'SeoDirectory') {
                $bfDirectoryTitle = trim(
                    (string) data_get(
                        $bfSeoProps,
                        'title',
                        'Businesses in Nigeria'
                    )
                );
                $bfSeoPageTitle =
                    $bfDirectoryTitle . ' - ' . $bfSeoAppName;
                $bfSeoDescription = trim(
                    (string) data_get(
                        $bfSeoProps,
                        'description',
                        'Find published Nigerian businesses on BusinessFinder Nigeria.'
                    )
                );

                $bfBaseCanonical = trim(
                    (string) data_get(
                        $bfSeoProps,
                        'canonicalUrl',
                        ''
                    )
                );

                $bfPagination = data_get(
                    $bfSeoProps,
                    'pagination'
                );

                $bfCurrentPage = max(
                    1,
                    (int) data_get(
                        $bfSeoProps,
                        'pagination.currentPage',
                        1
                    )
                );

                $bfLastPage = max(
                    1,
                    (int) data_get(
                        $bfSeoProps,
                        'pagination.lastPage',
                        1
                    )
                );

                $bfCurrentBusinessCount = collect(
                    data_get(
                        $bfSeoProps,
                        'businesses',
                        []
                    )
                )->count();

                $bfBaseIndexable = (bool) data_get(
                    $bfSeoProps,
                    'indexable',
                    false
                );

                $bfPageIndexable =
                    $bfBaseIndexable
                    && (
                        $bfPagination === null
                        || (
                            $bfCurrentPage <= $bfLastPage
                            && (
                                $bfCurrentPage === 1
                                || $bfCurrentBusinessCount > 0
                            )
                        )
                    );

                $bfSeoRobots = $bfPageIndexable
                    ? 'index,follow'
                    : 'noindex,follow';

                if ($bfBaseCanonical !== '') {
                    if (
                        $bfPagination !== null
                        && $bfCurrentPage > 1
                    ) {
                        $bfSeoCanonical =
                            $bfBaseCanonical
                            . (
                                str_contains(
                                    $bfBaseCanonical,
                                    '?'
                                )
                                    ? '&'
                                    : '?'
                            )
                            . 'page='
                            . $bfCurrentPage;
                    } else {
                        $bfSeoCanonical = $bfBaseCanonical;
                    }
                }

                $bfSeoPrev = data_get(
                    $bfSeoProps,
                    'pagination.prevUrl'
                );
                $bfSeoNext = data_get(
                    $bfSeoProps,
                    'pagination.nextUrl'
                );
                $bfSeoOgTitle = $bfDirectoryTitle;
            }
        @endphp

        <x-inertia::head>
            <title>{{ $bfSeoPageTitle }}</title>

            @if ($bfSeoDescription)
                <meta
                    data-inertia="description"
                    name="description"
                    content="{{ $bfSeoDescription }}"
                >
            @endif

            @if ($bfSeoRobots)
                <meta
                    data-inertia="robots"
                    name="robots"
                    content="{{ $bfSeoRobots }}"
                >
            @endif

            @if ($bfSeoCanonical)
                <link
                    data-inertia="canonical"
                    rel="canonical"
                    href="{{ $bfSeoCanonical }}"
                >
            @endif

            @if ($bfSeoOgTitle)
                <meta
                    data-inertia="og:title"
                    property="og:title"
                    content="{{ $bfSeoOgTitle }}"
                >
            @endif

            @if ($bfSeoDescription)
                <meta
                    data-inertia="og:description"
                    property="og:description"
                    content="{{ $bfSeoDescription }}"
                >
            @endif

            @if ($bfSeoCanonical)
                <meta
                    data-inertia="og:url"
                    property="og:url"
                    content="{{ $bfSeoCanonical }}"
                >
            @endif

            @if ($bfSeoOgTitle)
                <meta
                    data-inertia="og:type"
                    property="og:type"
                    content="{{ $bfSeoOgType }}"
                >
            @endif

            @if ($bfSeoPrev)
                <link
                    data-inertia="prev"
                    rel="prev"
                    href="{{ $bfSeoPrev }}"
                >
            @endif

            @if ($bfSeoNext)
                <link
                    data-inertia="next"
                    rel="next"
                    href="{{ $bfSeoNext }}"
                >
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

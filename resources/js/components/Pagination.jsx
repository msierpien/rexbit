import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function Pagination({ 
    data, 
    className = "", 
    showPages = 5,
    preserveScroll = false 
}) {
    if (!data?.links || data.links.length <= 3) {
        return null;
    }

    const { links, current_page, last_page, per_page, total } = data;
    
    // Calculate visible page numbers
    const getVisiblePages = () => {
        const half = Math.floor(showPages / 2);
        let start = Math.max(current_page - half, 1);
        let end = Math.min(start + showPages - 1, last_page);

        if (end - start + 1 < showPages) {
            start = Math.max(end - showPages + 1, 1);
        }

        const pages = [];
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        return pages;
    };

    const visiblePages = getVisiblePages();

    return (
        <div className={`flex items-center justify-between ${className}`}>
            {/* Results info */}
            <div className="text-sm text-gray-700 dark:text-gray-300">
                Wyświetlanie{' '}
                <span className="font-medium">
                    {((current_page - 1) * per_page) + 1}
                </span>
                {' '}do{' '}
                <span className="font-medium">
                    {Math.min(current_page * per_page, total)}
                </span>
                {' '}z{' '}
                <span className="font-medium">{total}</span>
                {' '}wyników
            </div>

            {/* Pagination links */}
            <div className="flex items-center space-x-1">
                {/* Previous page */}
                {links[0]?.url ? (
                    <Link
                        href={links[0].url}
                        preserveScroll={preserveScroll}
                        className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        <span className="sr-only">Poprzednia</span>
                    </Link>
                ) : (
                    <span className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                        <ChevronLeft className="h-4 w-4" />
                        <span className="sr-only">Poprzednia</span>
                    </span>
                )}

                {/* First page */}
                {visiblePages[0] > 1 && (
                    <>
                        <Link
                            href={`?page=1`}
                            preserveScroll={preserveScroll}
                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        >
                            1
                        </Link>
                        {visiblePages[0] > 2 && (
                            <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                        )}
                    </>
                )}

                {/* Visible page numbers */}
                {visiblePages.map((page) => (
                    page === current_page ? (
                        <span
                            key={page}
                            className="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-600 text-sm font-medium text-white cursor-default"
                        >
                            {page}
                        </span>
                    ) : (
                        <Link
                            key={page}
                            href={`?page=${page}`}
                            preserveScroll={preserveScroll}
                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        >
                            {page}
                        </Link>
                    )
                ))}

                {/* Last page */}
                {visiblePages[visiblePages.length - 1] < last_page && (
                    <>
                        {visiblePages[visiblePages.length - 1] < last_page - 1 && (
                            <span className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                        )}
                        <Link
                            href={`?page=${last_page}`}
                            preserveScroll={preserveScroll}
                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        >
                            {last_page}
                        </Link>
                    </>
                )}

                {/* Next page */}
                {links[links.length - 1]?.url ? (
                    <Link
                        href={links[links.length - 1].url}
                        preserveScroll={preserveScroll}
                        className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <ChevronRight className="h-4 w-4" />
                        <span className="sr-only">Następna</span>
                    </Link>
                ) : (
                    <span className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                        <ChevronRight className="h-4 w-4" />
                        <span className="sr-only">Następna</span>
                    </span>
                )}
            </div>
        </div>
    );
}
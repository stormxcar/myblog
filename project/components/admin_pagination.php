<?php

if (!function_exists('admin_render_numeric_pagination')) {
    /**
     * Render reusable numeric pagination for admin pages.
     *
     * @param int $page Current page (1-based)
     * @param int $totalPages Total pages
     * @param callable $urlBuilder Callback that receives page number and returns URL
     * @param string $linkAttributes Extra attributes for links (e.g. data-admin-ajax-link="1")
     * @param int $radius Number of page links shown around current page
     */
    function admin_render_numeric_pagination(int $page, int $totalPages, callable $urlBuilder, string $linkAttributes = '', int $radius = 2): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $page = max(1, min($page, $totalPages));
        $radius = max(1, $radius);

        $start = max(1, $page - $radius);
        $end = min($totalPages, $page + $radius);

        if ($start > 1) {
            $start = max(1, $start - 1);
        }

        if ($end < $totalPages) {
            $end = min($totalPages, $end + 1);
        }

        $attrs = trim($linkAttributes);
        if ($attrs !== '') {
            $attrs = ' ' . $attrs;
        }

        $html = '<nav aria-label="Phan trang" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">';

        if ($page > 1) {
            $prevUrl = htmlspecialchars((string)$urlBuilder($page - 1), ENT_QUOTES, 'UTF-8');
            $html .= '<a' . $attrs . ' class="option-btn ui-btn-warning" href="' . $prevUrl . '" aria-label="Trang truoc">&lsaquo;</a>';
        }

        if ($start > 1) {
            $firstUrl = htmlspecialchars((string)$urlBuilder(1), ENT_QUOTES, 'UTF-8');
            $html .= '<a' . $attrs . ' class="option-btn ui-btn-warning" href="' . $firstUrl . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="ui-muted" style="padding:0 .35rem;">...</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $pageUrl = htmlspecialchars((string)$urlBuilder($i), ENT_QUOTES, 'UTF-8');
            if ($i === $page) {
                $html .= '<span class="btn ui-btn" style="pointer-events:none;opacity:.95;">' . $i . '</span>';
            } else {
                $html .= '<a' . $attrs . ' class="option-btn ui-btn-warning" href="' . $pageUrl . '">' . $i . '</a>';
            }
        }

        if ($end < $totalPages) {
            if ($end < ($totalPages - 1)) {
                $html .= '<span class="ui-muted" style="padding:0 .35rem;">...</span>';
            }
            $lastUrl = htmlspecialchars((string)$urlBuilder($totalPages), ENT_QUOTES, 'UTF-8');
            $html .= '<a' . $attrs . ' class="option-btn ui-btn-warning" href="' . $lastUrl . '">' . $totalPages . '</a>';
        }

        if ($page < $totalPages) {
            $nextUrl = htmlspecialchars((string)$urlBuilder($page + 1), ENT_QUOTES, 'UTF-8');
            $html .= '<a' . $attrs . ' class="option-btn ui-btn-warning" href="' . $nextUrl . '" aria-label="Trang sau">&rsaquo;</a>';
        }

        $html .= '</nav>';

        return $html;
    }
}

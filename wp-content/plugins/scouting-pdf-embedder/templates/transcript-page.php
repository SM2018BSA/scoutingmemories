<?php
/**
 * One page of a document's text, with the links to the pages before and after it. Printed
 * inside the section when the post loads, and again each time the reader moves to another
 * page (the section swaps this piece in). $args: post, document (doc_key, viewer, pages), page.
 */

if (!defined('ABSPATH')) {
    exit;
}

$post = $args['post'];
$document = $args['document'];
$page = (int) $args['page'];
$numbers = array_keys($document['pages']);
$at = array_search($page, $numbers, true);
$previous = ($at !== false && $at > 0) ? $numbers[$at - 1] : 0;
$next = ($at !== false && $at < count($numbers) - 1) ? $numbers[$at + 1] : 0;
$text = isset($document['pages'][$page]) ? $document['pages'][$page] : '';

// A link that fetches a page into the section; without scripts it opens the post at that page
$turn = function ($to, $label) use ($post, $document) {
    printf(
        '<a class="btn btn-outline-secondary px-3 py-2" href="%s" hx-get="%s" hx-target="closest [data-pdf-text-area]" hx-swap="innerHTML">%s</a>',
        esc_url(Scouting_PDF_Transcript::page_link($post, $document['doc_key'], $to)),
        esc_url(Scouting_PDF_Transcript::fetch_url($post, $document['doc_key'], $to)),
        esc_html($label)
    );
};
?>
<div data-pdf-text-page="<?php echo esc_attr($page); ?>">
    <h3><?php echo esc_html(sprintf('Page %d', $page)); ?></h3>
    <?php if (trim($text) === '') : ?>
        <p>No text could be read from this page.</p>
    <?php else : ?>
        <p data-pdf-text-body><?php echo nl2br(esc_html($text)); ?></p>
    <?php endif; ?>
    <nav class="d-flex flex-wrap align-items-center gap-2" aria-label="Pages of the text">
        <?php
        if ($previous) {
            $turn($previous, 'Previous page');
        }
        if ($next) {
            $turn($next, 'Next page');
        }
        ?>
        <a class="btn btn-outline-secondary px-3 py-2" href="<?php echo esc_attr(Scouting_PDF_Transcript::page_hash($document['viewer'], $page)); ?>">Show this page in the viewer</a>
        <span class="text-muted"><?php echo esc_html(sprintf('Text page %d of %d', (int) $at + 1, count($numbers))); ?></span>
    </nav>
</div>

<?php
/**
 * One document's "Text of this document" section. Out of sight until the reader presses
 * Read text in the viewer (or opens a link to a page of text). $args: post, document
 * (doc_key, viewer, pages), several (the post has more than one document with text),
 * open (show it straight away), page (the page of text that comes with the post).
 * Stock Bootstrap classes and the theme's own headings only.
 */

if (!defined('ABSPATH')) {
    exit;
}

$document = $args['document'];
$heading_id = Scouting_PDF_Transcript::section_id($document['doc_key']) . '-heading';
?>
<?php /* hidden goes on a wrapper, so no display class of the card can undo it */ ?>
<div id="<?php echo esc_attr(Scouting_PDF_Transcript::section_id($document['doc_key'])); ?>" data-pdf-text
    data-pdf-text-doc="<?php echo esc_attr($document['doc_key']); ?>"
    data-pdf-text-viewer="<?php echo esc_attr($document['viewer']); ?>"
    data-pdf-text-url="<?php echo esc_url(Scouting_PDF_Transcript::fetch_url($args['post'], $document['doc_key'])); ?>"<?php echo empty($args['open']) ? ' hidden' : ''; ?>>
    <section class="card mt-4" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="card-header">
            <h2 class="card-title" id="<?php echo esc_attr($heading_id); ?>">Text of this document</h2>
            <?php if (!empty($args['several'])) : ?>
                <p class="mb-1"><strong><?php echo esc_html(sprintf('Document %d', $document['viewer'])); ?></strong></p>
            <?php endif; ?>
            <p class="mb-0">This text was read by a computer from the scanned pages and may contain mistakes. The scanned pages above are the original.</p>
        </div>
        <div class="card-body" data-pdf-text-area aria-live="polite">
            <?php Scouting_PDF_Transcript::render_page($args['post'], $document, $args['page']); ?>
        </div>
    </section>
</div>

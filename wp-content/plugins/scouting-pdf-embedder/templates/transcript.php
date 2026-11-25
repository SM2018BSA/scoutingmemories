<?php
/**
 * The "Text of this document" section. $args['documents'] is a list of
 * array('viewer' => n, 'pages' => page number => text) from Scouting_PDF_Transcript.
 * Stock Bootstrap classes and the theme's own headings only.
 */

if (!defined('ABSPATH')) {
    exit;
}

$documents = isset($args['documents']) ? (array) $args['documents'] : array();
$several = count($documents) > 1;
?>
<section class="card mt-4" data-pdf-text aria-labelledby="scouting-pdf-text-heading">
    <div class="card-header">
        <h2 class="card-title" id="scouting-pdf-text-heading">Text of this document</h2>
        <p class="mb-0">This text was read by a computer from the scanned pages and may contain mistakes. The scanned pages above are the original.</p>
    </div>
    <div class="card-body">
        <?php foreach ($documents as $document) : ?>
            <?php if ($several) : ?>
                <h3><?php echo esc_html(sprintf('Document %d', $document['viewer'])); ?></h3>
            <?php endif; ?>
            <?php foreach ($document['pages'] as $page => $text) : ?>
                <details class="border-bottom py-2" data-pdf-text-viewer="<?php echo esc_attr($document['viewer']); ?>" data-pdf-text-page="<?php echo esc_attr($page); ?>">
                    <summary><?php echo esc_html(sprintf('Page %d', $page)); ?></summary>
                    <?php if (trim($text) === '') : ?>
                        <p class="mt-2">No text could be read from this page.</p>
                    <?php else : ?>
                        <p class="mt-2" data-pdf-text-body><?php echo nl2br(esc_html($text)); ?></p>
                    <?php endif; ?>
                    <p class="mb-2">
                        <a class="btn btn-outline-secondary px-3 py-2" href="<?php echo esc_attr(Scouting_PDF_Transcript::page_hash($document['viewer'], $page)); ?>">Show this page in the viewer</a>
                    </p>
                </details>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</section>

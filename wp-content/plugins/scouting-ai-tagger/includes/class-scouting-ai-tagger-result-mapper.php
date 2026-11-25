<?php
/**
 * Turns the assistant's answer about one post into suggestions for the PDF Text plugin's
 * review list (PDF Text > Tag suggestions). Only values that are real terms on this site
 * are kept, and tags the post already has are left out. Nothing here tags a post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Result_Mapper {

	/** The answer's fields that the review list has a column for: field => taxonomy */
	const FIELDS = [ 'state', 'council', 'camp', 'lodge', 'start_date', 'end_date' ];

	const NO_EVIDENCE = 'Suggested by the AI from the document as a whole.';

	/**
	 * @param WP_Post $post   The post the answer is about.
	 * @param array   $result The "result" of a finished suggest job.
	 * @return array<int,array<string,mixed>> Rows for Scouting_PDF_Tag_Suggestions::add().
	 */
	public static function suggestions( WP_Post $post, array $result ): array {
		$fields = isset( $result['fields'] ) && is_array( $result['fields'] ) ? $result['fields'] : [];
		$place  = self::first_page( $post );
		$rows   = [];
		foreach ( self::FIELDS as $field ) {
			if ( ! taxonomy_exists( $field ) || empty( $fields[ $field ] ) || ! is_array( $fields[ $field ] ) ) {
				continue;
			}
			$given    = $fields[ $field ];
			$evidence = isset( $given['evidence'] ) && is_string( $given['evidence'] ) ? trim( $given['evidence'] ) : '';
			$values   = is_array( $given['value'] ?? null ) ? $given['value'] : [ $given['value'] ?? null ];
			foreach ( $values as $value ) {
				$found = self::term_for( $field, $value, $given );
				if ( null === $found || has_term( $found['has'], $field, $post ) ) {
					continue;
				}
				$rows[] = [
					'field'    => $field,
					'value'    => $found['value'],
					'label'    => $found['label'],
					'viewer'   => $place['viewer'],
					'page'     => $place['page'],
					'evidence' => '' !== $evidence ? $evidence : self::NO_EVIDENCE,
					'phrase'   => '',
					'source'   => 'ai',
				];
			}
		}
		return $rows;
	}

	/**
	 * The term a value stands for: ['value' => what the review list stores, 'label' => what
	 * the reviewer reads, 'has' => what has_term() is asked], or null when the site has no such term.
	 */
	private static function term_for( string $field, $value, array $given ): ?array {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( in_array( $field, [ 'start_date', 'end_date' ], true ) ) {
			// A year is a term's name; the review list makes the term when the site has none yet
			return preg_match( '/^(19|20)[0-9]{2}$/', $value ) ? [ 'value' => $value, 'label' => $value, 'has' => $value ] : null;
		}
		if ( in_array( $field, [ 'council', 'camp', 'lodge' ], true ) && empty( $given['validated'] ) ) {
			return null; // the assistant could not match it to its index
		}
		$term = get_term_by( 'slug', sanitize_title( $value ), $field );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		$label = class_exists( 'Scouting_PDF_Term_Names' ) ? Scouting_PDF_Term_Names::readable( $term->name, $field ) : $term->name;
		return [ 'value' => (int) $term->term_id, 'label' => $label, 'has' => (int) $term->term_id ];
	}

	/** The reviewer's Page link goes to the first read page of the post's first document. */
	private static function first_page( WP_Post $post ): array {
		foreach ( Scouting_PDF_Transcript::documents_in_viewer_order( $post ) as $document ) {
			foreach ( $document['pages'] as $page => $text ) {
				if ( '' !== trim( $text ) ) {
					return [ 'viewer' => (int) $document['viewer'], 'page' => (int) $page ];
				}
			}
		}
		return [ 'viewer' => 1, 'page' => 1 ];
	}
}

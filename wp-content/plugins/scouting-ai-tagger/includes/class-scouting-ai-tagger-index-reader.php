<?php
/**
 * Reads what the tagging assistant needs from this site's database: the council, camp,
 * lodge and state index that Formidable Forms keeps in its own tables, and the posts that
 * are already tagged (they become the assistant's examples).
 *
 * The form and field IDs are the same numbers as the theme's inc/formidable_constants.php
 * and the scouting-directory plugin's FormidableIndex. They are repeated here so this
 * plugin stands on its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Index_Reader {

	const FORM_STATES          = 10;
	const FORM_COUNCILS        = 8;
	const FORM_CAMPS           = 11;
	const FORM_LODGES          = 7;
	const FORM_COUNCIL_LINEAGE = 23; // "Councils Merged" rows on a council.
	const FORM_CAMP_COUNCILS   = 25; // "Link Additional Councils" rows on a camp.
	const FORM_LODGE_COUNCILS  = 27; // "Link Additional Councils" rows on a lodge.

	const STATE_NAME   = 113;
	const STATE_CODE   = 114;

	const COUNCIL_NAME   = 98;
	const COUNCIL_NUMBER = 138;
	const COUNCIL_STATES = 100;
	const COUNCIL_START  = 102;
	const COUNCIL_END    = 103;
	const COUNCIL_ACTIVE = 329;
	const COUNCIL_SLUG   = 105;

	const CAMP_NAME     = 117;
	const CAMP_STATES   = 207;
	const CAMP_COUNCILS = 116;
	const CAMP_START    = 120;
	const CAMP_END      = 121;
	const CAMP_ACTIVE   = 328;
	const CAMP_SLUG     = 123;

	const LODGE_NAME     = 90;
	const LODGE_NUMBER   = 91;
	const LODGE_STATES   = 325;
	const LODGE_COUNCILS = 89;
	const LODGE_START    = 94;
	const LODGE_END      = 95;
	const LODGE_ACTIVE   = 327;
	const LODGE_SLUG     = 97;

	const LINEAGE_EARLIER_COUNCIL = 210;
	const CAMP_EXTRA_COUNCIL      = 220;
	const LODGE_EXTRA_COUNCIL     = 279;

	const POSTS_PER_PAGE = 500;

	// Tags that are known data problems, corrected or dropped on the way out.
	const STATE_TAG_FIXES = [ '336' => 'NY' ];
	const JUNK_TAGS       = [ 'none', 'test_635' ];

	/** How many rows of each kind a full push will carry. */
	public function counts(): array {
		global $wpdb;
		$forms = [
			'states'   => self::FORM_STATES,
			'councils' => self::FORM_COUNCILS,
			'camps'    => self::FORM_CAMPS,
			'lodges'   => self::FORM_LODGES,
		];
		$out = [];
		foreach ( $forms as $name => $form_id ) {
			$out[ $name ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}frm_items WHERE form_id = %d", $form_id ) );
		}
		$out['posts'] = $this->post_count();
		return $out;
	}

	/** @return array<int,array<string,mixed>> rows in the shape the app's /v1/index/chunk takes */
	public function states(): array {
		$out = [];
		foreach ( $this->entries( self::FORM_STATES, [ self::STATE_NAME, self::STATE_CODE ] ) as $id => $m ) {
			$out[] = [
				'id'   => $id,
				'name' => $this->text( $m, self::STATE_NAME ),
				'code' => strtoupper( $this->text( $m, self::STATE_CODE ) ),
			];
		}
		return $out;
	}

	public function councils(): array {
		$fields = [ self::COUNCIL_NAME, self::COUNCIL_NUMBER, self::COUNCIL_STATES, self::COUNCIL_START, self::COUNCIL_END, self::COUNCIL_ACTIVE, self::COUNCIL_SLUG ];
		$out    = [];
		foreach ( $this->entries( self::FORM_COUNCILS, $fields ) as $id => $m ) {
			$out[] = $this->entity_row( $id, $m, self::COUNCIL_NAME, self::COUNCIL_STATES, 0, self::COUNCIL_START, self::COUNCIL_END, self::COUNCIL_ACTIVE, self::COUNCIL_SLUG, self::COUNCIL_NUMBER );
		}
		return $out;
	}

	public function camps(): array {
		$fields = [ self::CAMP_NAME, self::CAMP_STATES, self::CAMP_COUNCILS, self::CAMP_START, self::CAMP_END, self::CAMP_ACTIVE, self::CAMP_SLUG ];
		$out    = [];
		foreach ( $this->entries( self::FORM_CAMPS, $fields ) as $id => $m ) {
			$out[] = $this->entity_row( $id, $m, self::CAMP_NAME, self::CAMP_STATES, self::CAMP_COUNCILS, self::CAMP_START, self::CAMP_END, self::CAMP_ACTIVE, self::CAMP_SLUG, 0 );
		}
		return $out;
	}

	public function lodges(): array {
		$fields = [ self::LODGE_NAME, self::LODGE_NUMBER, self::LODGE_STATES, self::LODGE_COUNCILS, self::LODGE_START, self::LODGE_END, self::LODGE_ACTIVE, self::LODGE_SLUG ];
		$out    = [];
		foreach ( $this->entries( self::FORM_LODGES, $fields ) as $id => $m ) {
			$out[] = $this->entity_row( $id, $m, self::LODGE_NAME, self::LODGE_STATES, self::LODGE_COUNCILS, self::LODGE_START, self::LODGE_END, self::LODGE_ACTIVE, self::LODGE_SLUG, self::LODGE_NUMBER );
		}
		return $out;
	}

	/** Lineage plus the "Link Additional Councils" rows of camps and lodges. */
	public function links(): array {
		$out = [];
		foreach ( $this->child_links( self::FORM_COUNCIL_LINEAGE, self::LINEAGE_EARLIER_COUNCIL ) as $council => $earlier_ids ) {
			foreach ( $earlier_ids as $earlier ) {
				$out[] = [ 'relation' => 'lineage', 'parent_id' => $council, 'child_id' => $earlier ];
			}
		}
		foreach ( $this->child_links( self::FORM_CAMP_COUNCILS, self::CAMP_EXTRA_COUNCIL ) as $camp => $councils ) {
			foreach ( $councils as $council ) {
				$out[] = [ 'relation' => 'camp_council', 'parent_id' => $camp, 'child_id' => $council, 'role' => 'extra' ];
			}
		}
		foreach ( $this->child_links( self::FORM_LODGE_COUNCILS, self::LODGE_EXTRA_COUNCIL ) as $lodge => $councils ) {
			foreach ( $councils as $council ) {
				$out[] = [ 'relation' => 'lodge_council', 'parent_id' => $lodge, 'child_id' => $council, 'role' => 'extra' ];
			}
		}
		return $out;
	}

	public function post_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" );
	}

	public function post_pages(): int {
		return max( 1, (int) ceil( $this->post_count() / self::POSTS_PER_PAGE ) );
	}

	/**
	 * One page of already-tagged posts. Tags come from the taxonomy terms (clean slugs);
	 * the three descriptive values come from post meta.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function posts_page( int $page ): array {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY ID LIMIT %d OFFSET %d",
			self::POSTS_PER_PAGE,
			( max( 1, $page ) - 1 ) * self::POSTS_PER_PAGE
		) ) );
		if ( ! $ids ) {
			return [];
		}
		$in     = implode( ',', $ids );
		$titles = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($in)", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.

		$terms = $wpdb->get_results(
			"SELECT tr.object_id, tt.taxonomy, t.slug, t.name
			 FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE tr.object_id IN ($in)
			   AND tt.taxonomy IN ('category', 'state', 'council', 'camp', 'lodge', 'start_date', 'end_date')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.
		);
		$metas = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE post_id IN ($in) AND meta_key IN ('publisher_of_digital', 'meta_subject', 'identifier')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.
		);

		$rows = [];
		foreach ( $ids as $id ) {
			$rows[ $id ] = [
				'post_id'    => $id,
				'title'      => isset( $titles[ $id ] ) ? (string) $titles[ $id ]->post_title : '',
				'category'   => '',
				'states'     => [],
				'council'    => '',
				'camp'       => '',
				'lodge'      => '',
				'start_date' => '',
				'end_date'   => '',
				'publisher'  => '',
				'subject'    => '',
				'identifier' => '',
			];
		}
		foreach ( $terms as $t ) {
			$id   = (int) $t->object_id;
			$slug = (string) $t->slug;
			if ( in_array( $slug, self::JUNK_TAGS, true ) ) {
				continue;
			}
			switch ( $t->taxonomy ) {
				case 'category':
					if ( '' === $rows[ $id ]['category'] ) {
						$rows[ $id ]['category'] = (string) $t->name;
					}
					break;
				case 'state':
					$code = strtoupper( self::STATE_TAG_FIXES[ $slug ] ?? $slug );
					if ( strlen( $code ) <= 8 && ! in_array( $code, $rows[ $id ]['states'], true ) ) {
						$rows[ $id ]['states'][] = $code;
					}
					break;
				case 'council':
				case 'camp':
				case 'lodge':
					if ( '' === $rows[ $id ][ $t->taxonomy ] ) {
						$rows[ $id ][ $t->taxonomy ] = $slug;
					}
					break;
				case 'start_date':
				case 'end_date':
					if ( '' === $rows[ $id ][ $t->taxonomy ] ) {
						$rows[ $id ][ $t->taxonomy ] = $slug;
					}
					break;
			}
		}
		$meta_names = [ 'publisher_of_digital' => 'publisher', 'meta_subject' => 'subject', 'identifier' => 'identifier' ];
		foreach ( $metas as $m ) {
			$rows[ (int) $m->post_id ][ $meta_names[ $m->meta_key ] ] = mb_substr( trim( (string) $m->meta_value ), 0, 300 );
		}
		return array_values( $rows );
	}

	// -- the shared pieces --

	private function entity_row( int $id, array $m, int $name, int $states, int $councils, int $start, int $end, int $active, int $slug, int $number ): array {
		$status = 'yes' === strtolower( $this->text( $m, $active ) ) ? 'active' : 'closed';
		return [
			'id'       => $id,
			'name'     => $this->text( $m, $name ),
			'number'   => $number ? $this->text( $m, $number ) : '',
			'states'   => $this->ids( $m[ $states ] ?? '' ),
			'councils' => $councils ? $this->ids( $m[ $councils ] ?? '' ) : [],
			'start'    => $this->text( $m, $start ),
			// A still-active record's stored end year was filled in by a bulk tool: it is not real.
			'end'      => 'active' === $status ? '' : $this->text( $m, $end ),
			'status'   => $status,
			'slug'     => sanitize_title( $this->text( $m, $slug ) ),
		];
	}

	/** @return array<int,array<int,string>> entry ID => field ID => raw value */
	private function entries( int $form_id, array $field_ids ): array {
		global $wpdb;
		$in  = implode( ',', array_map( 'intval', $field_ids ) );
		$out = [];
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d ORDER BY id", $form_id ) );
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = [];
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.item_id, m.field_id, m.meta_value FROM {$wpdb->prefix}frm_item_metas m
				 JOIN {$wpdb->prefix}frm_items i ON i.id = m.item_id
				 WHERE i.form_id = %d AND m.field_id IN ($in)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
				$form_id
			)
		);
		foreach ( $rows as $r ) {
			$out[ (int) $r->item_id ][ (int) $r->field_id ] = (string) $r->meta_value;
		}
		return $out;
	}

	/** @return array<int,int[]> parent entry ID => linked entry IDs, without duplicates */
	private function child_links( int $child_form_id, int $field_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.parent_item_id, m.meta_value FROM {$wpdb->prefix}frm_items i
				 JOIN {$wpdb->prefix}frm_item_metas m ON m.item_id = i.id AND m.field_id = %d
				 WHERE i.form_id = %d AND i.parent_item_id > 0 ORDER BY i.id",
				$field_id,
				$child_form_id
			)
		);
		$out = [];
		foreach ( $rows as $r ) {
			foreach ( $this->ids( (string) $r->meta_value ) as $linked ) {
				$parent = (int) $r->parent_item_id;
				if ( ! in_array( $linked, $out[ $parent ] ?? [], true ) ) {
					$out[ $parent ][] = $linked;
				}
			}
		}
		return $out;
	}

	private function text( array $meta, int $field_id ): string {
		return trim( (string) ( $meta[ $field_id ] ?? '' ) );
	}

	/**
	 * Entry IDs from a Formidable "data" field: a plain ID or a serialized list of IDs.
	 *
	 * @return int[]
	 */
	private function ids( string $value ): array {
		$value = trim( $value );
		if ( '' === $value ) {
			return [];
		}
		if ( is_serialized( $value ) ) {
			$list = @unserialize( $value, [ 'allowed_classes' => false ] ); // phpcs:ignore -- stored by Formidable; no objects allowed.
			$list = is_array( $list ) ? array_values( $list ) : [];
		} else {
			$list = [ $value ];
		}
		$out = [];
		foreach ( $list as $v ) {
			if ( is_scalar( $v ) && ctype_digit( (string) $v ) && (int) $v > 0 && ! in_array( (int) $v, $out, true ) ) {
				$out[] = (int) $v;
			}
		}
		return $out;
	}
}

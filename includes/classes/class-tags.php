<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

/**
 * Class Tags
 *
 * Container for message tags when sending a tagged message and available tags in admin options pages
 *
 * @package Texteller
 */
class Tags
{
	/**
	 * @var array Available tags. Keys are tag-type slugs and values are associated tags
	 */
	private $tags = [];

	public function __construct() {}

	/**
	 * Gets tag data for a single tag.
	 *
	 * @param string $tag_type Tag type
	 * @param string $tag_slug Tag slug
	 * @return mixed $tag_data Tag label or processed tag value
	 */
	public function get_tag_data( string $tag_type, string $tag_slug )
	{
		return isset($this->tags[$tag_type][$tag_slug]) ? $this->tags[$tag_type][$tag_slug] : '';
	}

	/**
	 * Adds tag data for a single tag.
	 *
	 * @param string $tag_type Tag type
	 * @param string $tag_slug Tag slug
	 * @param mixed $tag_data Tag label or processed tag value
	 */
	public function add_tag_data( string $tag_type, string $tag_slug, $tag_data )
	{
		$this->tags[$tag_type ][ $tag_slug] = (string) $tag_data;
	}

	/**
	 * Adds tag data for the given tag-type.
	 *
	 * @param string $tag_type Tag type
	 * @param array $tags Array of tag's data, keys are tag IDs and values may be tag labels or processed tag values
	 */
	public function add_tag_type_data( string $tag_type, array $tags )
	{
		foreach ( $tags as $tag_id => $tag_data ) {
			$this->add_tag_data( $tag_type, $tag_id, $tag_data );
		}
	}

	/**
	 * Gets  merged tags data for multi tag-types
	 *
	 * @param array $tag_types Array of tag_types to get the data
	 * @return array Merged array of tag data for given tag types
	 */
	public function get_merged_tag_types_data( array $tag_types )
	{
		$tags = [];

		foreach ( $tag_types as $tag_type ) {
			$tags_part =  isset( $this->tags[$tag_type] ) ? $this->tags[$tag_type] : [];
			$tags = array_merge( $tags, $tags_part );
		}

		return $tags;
	}
}
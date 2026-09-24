<?php
class Query {

	public static $relation_and = ['relation' => 'AND'];
	public static $relation_or  = ['relation' => 'AND'];


	static function create_query($key = null, $value = null, $comparison = null) {

		return ['key' => $key, 'value' => $value, 'compare' => $comparison];

	}



}
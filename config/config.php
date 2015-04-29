<?php

$config = [
	'pagination' => [
		'default_profile' => 'default',
		'profiles' => [
			'default' => [
				'page_uri_id'        => 'pagn',
				'limit_uri_id'       => 'limit',
				'css'                => 'pagination/styles.css',
				'limit'              => 10,
				'allow_limit_change' => true,
				'allowed_limits'     => [10,25,100],
				'adjacent'           => 1
			]
		]
	]
];

?>
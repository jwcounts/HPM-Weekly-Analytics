<?php
	// Create connection to Google Analytics
	function initializeAnalytics() {
		$client = new Google_Client();
		$client->setApplicationName( "Hello Analytics Reporting" );
		$client->setAuthConfig( GA_CLIENT );
		$client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
		$analytics = new Google_Service_Analytics( $client );
		return $analytics;
	}

	/**
	 * We have our main Google Analytics property for the site, as well as a separate property for Google AMP
	 * If you only have one you want to check, or more than two, modify this array
	 */
	$gas = [
		'Main' => GA_MAIN,
		'AMP' => GA_AMP
	];

	// Setting up device colors for the graphing application
	$ga_device_colors = [
		'desktop' => 'rgba(255,0,0,1)',
		'tablet' => 'rgba(0,0,255,1)',
		'mobile' => 'rgba(0,255,0,1)'
	];

	$analytics = initializeAnalytics();
	foreach ( $gas as $g => $ga ) :
		$ga_acct_name = 'ga-'.strtolower( $g );

		// Pull article numbers from GA
		$result = $analytics->data_ga->get(
			'ga:'.$ga,
			$start,
			$end,
			'ga:visits',
			[
				'filters' => 'ga:pagePath=@/articles',
				'dimensions' => 'ga:pagePath',
				'metrics' => 'ga:pageviews,ga:uniquePageviews',
				'sort' => '-ga:pageviews,-ga:uniquePageviews',
				'max-results' => $num,
				'output' => 'json'
			]
		);

		$sheet = 'Top Stories ('.$g.')';
		$sheets[$sheet] = [];


		// Parsing the numbers from GA
		foreach ( $result->rows as $k => $row ) :
			// Set up the title row in the spreadsheet
			if ( $k == 0 ) :
				$sheets[$sheet][] = [
					'Article Info', '', '', '', '', 'Pageviews', '', 'Pageviews from Source', '', '', '', '', '', 'Source Types', '', '', '', ''
				];
				$sheets[$sheet][] = [
					'Title', 'URL', 'Author', 'Date', 'Categories and Tags', 'Total', 'Unique', 'Direct', 'Google', 'Facebook', 'Twitter', 'Bing Search', 'Yahoo Search', 'Direct/No Referrer', 'Organic', 'Email', 'Referral', 'Social'
				];
			endif;

			/**
			 * Since we're on a Wordpress site, I am using the WP-JSON API to pull additional information
			 * 		about each article, such as categories, tags, publish date, and authors
			 */
			preg_match( '/\/articles\/[a-z0-9\-\/]+\/[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/([0-9]+)\/.+/', $row[0], $match );
			if ( !empty( $match ) ) :
				$id = $match[1];
				$post = file_get_contents( 'https://www.houstonpublicmedia.org/wp-json/wp/v2/posts/'.$id );
				$cats = file_get_contents( 'https://www.houstonpublicmedia.org/wp-json/wp/v2/categories?post='.$id );
				$pjs = json_decode( $post );
				$catjs = json_decode( $cats );
				$title = html_entity_decode( str_replace( $find, $replace, $pjs->title->rendered ), ENT_QUOTES, 'UTF-8' );
				$date = strtotime( $pjs->date );
				$authors = $tags = [];
				foreach( $pjs->coauthors as $coa ) :
					$authors[] = $coa->display_name;
				endforeach;
				foreach( $catjs as $ca ) :
					$tags[] = html_entity_decode( $ca->name );
				endforeach;
			endif;
			$date_format = date( 'Y-m-d g:i A', $date );

			// Secondary GA pull to gather source / medium information for each article
			$sources = $analytics->data_ga->get(
				'ga:'.$ga,
				$start,
				$end,
				'ga:visits',
				[
					'filters' => 'ga:pagePath=@'.$row[0],
					'dimensions' => 'ga:sourceMedium',
					'metrics' => 'ga:pageviews',
					'sort' => '-ga:pageviews',
					'output' => 'json'
				]
			);
			$google = $facebook = $twitter = $bing = $yahoo = $direct = $organic = $email = $referral = $social = '0';

			// Parsing the source / medium pull from GA
			foreach ( $sources->rows as $source ) :
				$source_ex = explode( '/', $source[0] );
				$medium = trim( $source_ex[1] );
				$stype = trim( $source_ex[0] );
				if ( $medium == '(none)' ) :
					$direct += $source[1];
				elseif ( $medium == 'organic' ) :
					$organic += $source[1];
				elseif ( $medium == 'social' ) :
					$social += $source[1];
				elseif ( $medium == 'email' ) :
					$email += $source[1];
				elseif ( $medium == 'referral' ) :
					$referral += $source[1];
				endif;
				if ( strpos( $stype, 'google' ) !== false ) :
					$google += $source[1];
				elseif ( strpos( $stype, 'bing' ) !== false ) :
					$bing += $source[1];
				elseif ( strpos( $stype, 'facebook' ) !== false ) :
					$facebook += $source[1];
				elseif ( strpos( $stype, 't.co' ) !== false ) :
					$twitter += $source[1];
				elseif ( strpos( $stype, 'yahoo' ) !== false ) :
					$yahoo += $source[1];
				endif;
			endforeach;

			// Adding the row to the sheet
			$sheets[$sheet][] = [
				$title,
				'https://www.houstonpublicmedia.org'.$row[0],
				implode( ' / ', $authors ),
				$date_format,
				implode( ', ', $tags ),
				$row[1],
				$row[2],
				$direct,
				$google,
				$facebook,
				$twitter,
				$bing,
				$yahoo,
				$direct,
				$organic,
				$email,
				$referral,
				$social
			];

			// Mapping the data into the graphing data
			$graphs[$ga_acct_name.'-articles']['labels'][] = $title;
			$graphs[$ga_acct_name.'-articles']['datasets'][0]['data'][] = $direct;
			$graphs[$ga_acct_name.'-articles']['datasets'][1]['data'][] = $google;
			$graphs[$ga_acct_name.'-articles']['datasets'][2]['data'][] = $facebook;
			$graphs[$ga_acct_name.'-articles']['datasets'][3]['data'][] = $twitter;
			$graphs[$ga_acct_name.'-articles']['datasets'][4]['data'][] = $bing;
			$graphs[$ga_acct_name.'-articles']['datasets'][5]['data'][] = $yahoo;
		endforeach;

		// User / Session pull from GA
		$result2 = $analytics->data_ga->get(
			'ga:'.$ga,
			$start,
			$end,
			'ga:visits',
			[
				'dimensions' => 'ga:year,ga:month,ga:day,ga:hour',
				'metrics' => 'ga:sessions,ga:users',
				'sort' => 'ga:year,ga:month,ga:day,ga:hour',
				'output' => 'json'
			]
		);

		$result4 = $analytics->data_ga->get(
			'ga:'.$ga,
			$start,
			$end,
			'ga:visits',
			[
				'dimensions' => 'ga:year,ga:month',
				'metrics' => 'ga:users',
				'sort' => 'ga:year,ga:month',
				'output' => 'json'
			]
		);

		$graphs['overall-totals'][$ga_acct_name]['data'] += $result4->totalsForAllResults['ga:users'];

		// New sheet
		$sheet = 'Hourly Stats ('.$g.')';
		$sheets[$sheet] = [];

		// Parsing User / Session results
		foreach ( $result2->rows as $k => $row ) :
			if ( $k == 0 ) :
				$sheets[$sheet][] = [
					'Day and Time', 'Sessions', 'Users'
				];
			endif;
			$sheets[$sheet][] = [
				$row[1].'/'.$row[2].'/'.$row[0].' '.$row[3].':00', $row[4], $row[5]
			];
			$graphs[$ga_acct_name.'-hourly']['labels'][] = $row[1].'/'.$row[2].'/'.$row[0].' '.$row[3].':00';
			$graphs[$ga_acct_name.'-hourly']['datasets'][0]['data'][] = $row[5];
		endforeach;

		// Inserting blank rows
		$sheets[$sheet][] = [
			'', '', ''
		];
		$sheets[$sheet][] = [
			'', '', ''
		];

		// Pulling device category stats from GA
		$result3 = $analytics->data_ga->get(
			'ga:'.$ga,
			$start,
			$end,
			'ga:visits',
			[
				'dimensions' => 'ga:deviceCategory',
				'metrics' => 'ga:sessions,ga:users',
				'sort' => '-ga:users',
				'output' => 'json'
			]
		);

		// Parsing
		foreach ( $result3->rows as $k => $row ) :
			if ( $k == 0 ) :
				$sheets[$sheet][] = [
					'Device Category', 'Sessions', 'Users'
				];
			endif;
			$sheets[$sheet][] = [
				ucwords( $row[0] ), $row[1], $row[2]
			];
			$graphs[$ga_acct_name.'-devices']['labels'][] = ucwords( $row[0] );
			$graphs[$ga_acct_name.'-devices']['datasets'][0]['data'][] = $row[2];
			$graphs[$ga_acct_name.'-devices']['datasets'][0]['backgroundColor'][] = $ga_device_colors[$row[0]];
		endforeach;

		// Inserting blank rows
		$sheets[$sheet][] = [
			'', '', ''
		];
		$sheets[$sheet][] = [
			'', '', ''
		];

		// Overall information
		$sheets[$sheet][] = [
			'Total Sessions', $result2->totalsForAllResults['ga:sessions']
		];
		$sheets[$sheet][] = [
			'Total Users', $result2->totalsForAllResults['ga:users']
		];
	endforeach;
?>
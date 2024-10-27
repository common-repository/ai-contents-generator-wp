<?php

class aiwrc_ContentsComAPI
{

	/**
	 * just an imploder of strings
	 *
	 * @param mixed $messages
	 * @return string
	 */
	static function _messages_to_string($messages)
	{
		$s = '';
		if (is_string($messages)) {
			$s = $messages;
		}
		if (is_array($messages)) {
			foreach ($messages as $parameter => $errors) {
				$s .= $parameter . ' = ' . implode(' ', $errors) . ' ';
			}
		}
		return $s;
	}

	static function login($email, $password)
	{
		$response = wp_remote_post('https://dashboard.contents.com/api/services/auth/login', array(
			'timeout' => 15,
			'redirection' => 5,
			'httpversion' => '1.0',
			//'reject_unsafe_urls' => false,
			'blocking' => true,
			//'sslverify' => false,
			'headers' => array(
				'Content-Type' => 'application/json'
			),
			'data_format' => 'body',
			'body' => wp_json_encode(array(
				'email' => $email,
				'password' => $password
			)),
			'cookies' => array()
		));

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			return array(false, 'WP err: ' . $error_message);
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (is_callable(array('AiWriterContentsCom', 'log'))) {
			AiWriterContentsCom::log($body);
		}

		if (null === $body) {
			return array(false, 'API err: failed to decode response');
		}

		if (!isset($body['status'])) {
			return array(false, 'API err: unknown response, status is missing');
		}

		if ('error' === $body['status']) {
			return array(false, 'API errors: ' . $body['message']);
		}

		if (!isset($body['service-api'])) {
			return array(false, 'API err: service-api field missing');
		}

		if (!isset($body['user'])) {
			return array(false, 'API err: user field missing');
		}

		return array(true, $body); // return whole body, 'service-api' and 'user'
	}

	static function api_article_generator__titles($token, $user_id, $title, $input, $language, $article_len, $tov = 'neutral', $website_id = null)
	{
		$title = substr($title, 0, 1000); // ??
		$input = substr($input, 0, 5000);

		$body = array(
			'product_key' => 'article_generator',
			'user_id' => (int)$user_id,
			'product_type' =>  'titles',
			'title' => $title,
			'input' => $input,
			'language' => $language,
			'article_len' => (int)$article_len,
			'tov' => $tov,
			'source' => 'ag-wordpress-plugin',
			'fast_api' => true
		);

		if (null !== $website_id) {
			$body['website_id'] = (int)$website_id;
		}

		$response = wp_remote_request('https://dashboard.contents.com/api/services/orders/create', array(
			'timeout' => 240,
			'redirection' => 5,
			'httpversion' => '1.0',
			//'reject_unsafe_urls' => false,
			'blocking' => true,
			//'sslverify' => false,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => "Bearer " . $token,
			),
			// 'data_format' => 'body',
			'method' => 'PUT',
			'body' => wp_json_encode($body),
			'cookies' => array()
		));

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			return array(false, 'WP err: ' . $error_message);
		}

		$body_encoded = wp_remote_retrieve_body($response);
		$body = json_decode($body_encoded, true);

		if (null === $body) {
			if (is_callable(array('AiWriterContentsCom', 'log'))) {
				AiWriterContentsCom::log($body_encoded);
			}
			if (false !== stripos($body_encoded, '<title>Server Error</title>')) {
				return array(false, 'API error: server error');
			}
			else {
				return array(false, 'API error: failed to decode response');
			}
		}

		if (is_callable(array('AiWriterContentsCom', 'log'))) {
			AiWriterContentsCom::log($body);
		}

		if (!isset($body['status']) && isset($body['code']) && isset($body['error_key']) && isset($body['message'])) {
			return array(false, sprintf('API error %s: %s; %s', $body['code'], $body['error_key'], $body['message']));
		}

		if (!isset($body['status'])) {
			return array(false, 'API error: unknown response, status is missing');
		}

		if ('error' === $body['status']) {
			if ('services_not_available_for_your_subscription' === $body['message']) {
				return array(false, 'This plugin is only available to Advanced subscribers. Upgrade today to get high-quality, instantly-generated AI content');
			}
			else {
				return array(false, 'API errors: ' . self::_messages_to_string($body['message']));
			}
		}

		// on err
		/*
		Array
		(
		    [status] => error
		    [message] => Array
		        (
		            [input] => Array
		                (
		                    [0] => The input field is required when product type is titles.
		                )

		        )

		)
		*/

		/*
		$body here:
		Array
		(
		    [status] => success
		    [message] => Titles created with success!
		    [order_id] => 285664
		    [order_detail_id] => 300598
		    [result] => The history and diversity of Italian cuisine |The most famous Italian dishes and their regional variations |The importance of fresh ingredients and simplicity in Italian cooking
		)
		*/

		if (isset($body['result']) && 'Error generating instant results' === $body['result']) {
			return array(false, 'API error: error generating instant results');
		}

		// convenience server side explosion
		if (isset($body['result'])) {
			$body['result'] = explode('|', $body['result']);
		}

		return array(true, $body); // return whole body
	}

	static function api_article_generator__paragraphs($token, $user_id, $paragraphs, $order_detail_id)
	{
		$body = array(
			'product_key' => 'article_generator',
			'user_id' => (int)$user_id,
			'product_type' =>  'paragraphs',
			'title_paragraphs' => $paragraphs,
			'order_det_id' => (int)$order_detail_id,
			'source' => 'ag-wordpress-plugin',
			'fast_api' => true
		);

		$response = wp_remote_request('https://dashboard.contents.com/api/services/orders/create', array(
			'timeout' => 240,
			'redirection' => 5,
			'httpversion' => '1.0',
			//'reject_unsafe_urls' => false,
			'blocking' => true,
			//'sslverify' => false,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => "Bearer " . $token,
			),
			// 'data_format' => 'body',
			'method' => 'PUT',
			'body' => wp_json_encode($body),
			'cookies' => array()
		));

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			return array(false, 'WP err: ' . $error_message);
		}

		$body_encoded = wp_remote_retrieve_body($response);
		$body = json_decode($body_encoded, true);

		if (null === $body) {
			if (is_callable(array('AiWriterContentsCom', 'log'))) {
				AiWriterContentsCom::log(__METHOD__, '$body_encoded on failed decoding', $body_encoded);
			}
			if (false !== stripos($body_encoded, '<title>Server Error</title>')) {
				return array(false, 'API error: server error');
			}
			elseif (false !== stripos($body_encoded, 'A timeout occurred')) {
				return array(false, 'API error: a remote server timeout occurred');
			}
			else {
				return array(false, 'API error: failed to decode response');
			}
		}

		if (is_callable(array('AiWriterContentsCom', 'log'))) {
			AiWriterContentsCom::log($body);
		}

		if (!isset($body['status']) && isset($body['code']) && isset($body['error_key']) && isset($body['message'])) {
			return array(false, sprintf('API error %s: %s; %s', $body['code'], $body['error_key'], $body['message']));
		}

		if (!isset($body['status'])) {
			return array(false, 'API error: unknown response, status is missing');
		}

		if ('error' === $body['status']) {
			return array(false, 'API errors: ' . self::_messages_to_string($body['message']));
		}

		// on err
		/*
		Array
		(
		    [status] => error
		    [message] => Array
		        (
		            [input] => Array
		                (
		                    [0] => The input field is required when product type is titles.
		                )

		        )

		)
		*/

		/*
		$body here:
		Array
		(
		    [status] => success
		    [message] => Titles created with success!
		    [order_id] => 285664
		    [order_detail_id] => 300598
		    [result] => The history and diversity of Italian cuisine |The most famous Italian dishes and their regional variations |The importance of fresh ingredients and simplicity in Italian cooking
		)
		*/

		if (isset($body['result']) && 'Error generating instant results' === $body['result']) {
			return array(false, 'API error: error generating instant results');
		}

		return array(true, $body); // return whole body
	}
}

class aiwrc_AIWRCHelper
{
	static function getSelectHTML($args = array())
	{
		$args = array_merge(array(
			'name' => 'myselect',
			'options' => array(), // array of labels => values
			'class' => '',
			'selected' => null
		), $args);
		ob_start();
		?>
		<select name="<?php echo esc_attr($args['name']); ?>" class="<?php echo esc_attr($args['class']); ?>">
		<?php foreach($args['options'] as $label => $value): ?>
			<option value="<?php echo esc_attr($value); ?>" <?php selected($args['selected'], $value); ?>><?php echo esc_html($label); ?></option>
		<?php endforeach; ?>
		</select>
		<?php
		return $ob = ob_get_clean();
	}
}

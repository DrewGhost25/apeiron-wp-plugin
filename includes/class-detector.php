<?php
defined( 'ABSPATH' ) || exit;

/**
 * Apeiron_Detector — riconosce i bot AI dal loro User-Agent.
 */
class Apeiron_Detector {

	const KNOWN_BOTS = [
		'GPTBot'             => [ 'name' => 'OpenAI GPTBot',        'company' => 'OpenAI',       'purpose' => 'training' ],
		'ChatGPT-User'       => [ 'name' => 'OpenAI ChatGPT',       'company' => 'OpenAI',       'purpose' => 'browsing' ],
		'OAI-SearchBot'      => [ 'name' => 'OpenAI Search',        'company' => 'OpenAI',       'purpose' => 'search' ],
		'ClaudeBot'          => [ 'name' => 'Anthropic Claude',     'company' => 'Anthropic',    'purpose' => 'training' ],
		'Claude-Web'         => [ 'name' => 'Anthropic Claude Web', 'company' => 'Anthropic',    'purpose' => 'browsing' ],
		'anthropic-ai'       => [ 'name' => 'Anthropic AI',         'company' => 'Anthropic',    'purpose' => 'training' ],
		'Google-Extended'    => [ 'name' => 'Google Gemini',        'company' => 'Google',       'purpose' => 'training' ],
		'GoogleOther'        => [ 'name' => 'Google Other',         'company' => 'Google',       'purpose' => 'various' ],
		'PerplexityBot'      => [ 'name' => 'Perplexity',           'company' => 'Perplexity',   'purpose' => 'search' ],
		'FacebookBot'        => [ 'name' => 'Meta AI',              'company' => 'Meta',         'purpose' => 'training' ],
		'Meta-ExternalAgent' => [ 'name' => 'Meta External',        'company' => 'Meta',         'purpose' => 'training' ],
		'YouBot'             => [ 'name' => 'You.com',              'company' => 'You.com',      'purpose' => 'search' ],
		'Diffbot'            => [ 'name' => 'Diffbot',              'company' => 'Diffbot',      'purpose' => 'data' ],
		'CCBot'              => [ 'name' => 'Common Crawl',         'company' => 'Common Crawl', 'purpose' => 'training' ],
		'Amazonbot'          => [ 'name' => 'Amazon',               'company' => 'Amazon',       'purpose' => 'various' ],
		'Applebot'           => [ 'name' => 'Apple AI',             'company' => 'Apple',        'purpose' => 'training' ],
		'Applebot-Extended'  => [ 'name' => 'Apple AI Extended',    'company' => 'Apple',        'purpose' => 'training' ],
		'Bingbot'            => [ 'name' => 'Microsoft Copilot',    'company' => 'Microsoft',    'purpose' => 'search' ],
		'ByteSpider'         => [ 'name' => 'TikTok ByteDance',     'company' => 'ByteDance',    'purpose' => 'training' ],
		'Cohere-ai'          => [ 'name' => 'Cohere',               'company' => 'Cohere',       'purpose' => 'training' ],
		'Mistral-AI'         => [ 'name' => 'Mistral',              'company' => 'Mistral',      'purpose' => 'training' ],
	];

	/**
	 * Rileva se lo User-Agent corrisponde a un bot AI noto.
	 *
	 * @param string $user_agent
	 * @return array ['detected'=>bool, e opzionalmente 'bot_key', 'name', 'company', 'purpose']
	 */
	public function detect( string $user_agent ): array {
		foreach ( self::KNOWN_BOTS as $key => $info ) {
			if ( stripos( $user_agent, $key ) !== false ) {
				return [
					'detected' => true,
					'bot_key'  => $key,
					'name'     => $info['name'],
					'company'  => $info['company'],
					'purpose'  => $info['purpose'],
				];
			}
		}
		return [ 'detected' => false ];
	}

	/**
	 * Ritorna l'array completo dei bot conosciuti.
	 *
	 * @return array
	 */
	public function get_known_bots(): array {
		return self::KNOWN_BOTS;
	}
}

<?php
/**
 * Plain-English advice for Lighthouse audits and insights.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Psi;

/**
 * Maps Lighthouse audit IDs (classic audits and the newer "insights") to topics,
 * so the same problem reported twice only shows once, with a plain-English fix.
 */
final class Advice {

	/**
	 * Audit ID => topic.
	 *
	 * @return array<string,string>
	 */
	public static function audit_topics() {
		return array(
			'render-blocking-resources'        => 'render-blocking',
			'render-blocking-insight'          => 'render-blocking',
			'unused-javascript'                => 'unused-js',
			'legacy-javascript'                => 'unused-js',
			'legacy-javascript-insight'        => 'unused-js',
			'duplicated-javascript'            => 'unused-js',
			'duplicated-javascript-insight'    => 'unused-js',
			'unused-css-rules'                 => 'unused-css',
			'unminified-css'                   => 'minify',
			'unminified-javascript'            => 'minify',
			'uses-optimized-images'            => 'images',
			'modern-image-formats'             => 'images',
			'uses-responsive-images'           => 'images',
			'efficient-animated-content'       => 'images',
			'image-delivery-insight'           => 'images',
			'offscreen-images'                 => 'lazy-load',
			'server-response-time'             => 'server',
			'document-latency-insight'         => 'server',
			'redirects'                        => 'redirects',
			'uses-text-compression'            => 'compression',
			'uses-long-cache-ttl'              => 'caching',
			'cache-insight'                    => 'caching',
			'lcp-lazy-loaded'                  => 'lcp',
			'prioritize-lcp-image'             => 'lcp',
			'largest-contentful-paint-element' => 'lcp',
			'lcp-discovery-insight'            => 'lcp',
			'lcp-phases-insight'               => 'lcp',
			'lcp-breakdown-insight'            => 'lcp',
			'layout-shifts'                    => 'layout-shift',
			'layout-shift-elements'            => 'layout-shift',
			'unsized-images'                   => 'layout-shift',
			'cls-culprits-insight'             => 'layout-shift',
			'font-display'                     => 'fonts',
			'font-display-insight'             => 'fonts',
			'third-party-summary'              => 'third-party',
			'third-parties-insight'            => 'third-party',
			'third-party-facades'              => 'third-party',
			'bootup-time'                      => 'js-work',
			'mainthread-work-breakdown'        => 'js-work',
			'long-tasks'                       => 'js-work',
			'forced-reflow-insight'            => 'js-work',
			'dom-size'                         => 'dom-size',
			'dom-size-insight'                 => 'dom-size',
			'total-byte-weight'                => 'page-weight',
			'critical-request-chains'          => 'request-chain',
			'network-dependency-tree-insight'  => 'request-chain',
			'uses-rel-preconnect'              => 'request-chain',
			'viewport'                         => 'viewport',
			'viewport-insight'                 => 'viewport',
		);
	}

	/**
	 * Topic => plain-English title, why, fix, and who usually fixes it.
	 *
	 * Who: "code" (theme/plugin work, iWorks can help), "hosting", "content" (site owner).
	 *
	 * @return array<string,array{title: string, why: string, fix: string, who: string}>
	 */
	public static function topics() {
		return array(
			'render-blocking' => array(
				'title' => __( 'Files are holding up the first paint', 'iworksie-speed-monitor' ),
				'why'   => __( 'The browser has to download some CSS and JavaScript files before it can show anything, so visitors stare at a blank screen.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Defer or delay non-essential scripts, inline the small amount of CSS needed for the top of the page, and remove plugins that load files on every page. A good caching or optimisation plugin can do much of this.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'unused-js'       => array(
				'title' => __( 'Too much JavaScript the page does not use', 'iworksie-speed-monitor' ),
				'why'   => __( 'Every script has to be downloaded and processed, even if the page never uses it. This slows loading and makes the page feel sluggish, especially on phones.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Find which plugins or theme features add the scripts and only load them on pages that need them. Remove plugins you no longer use, and avoid sliders, page builders or widgets that load large libraries everywhere.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'unused-css'      => array(
				'title' => __( 'Too much CSS the page does not use', 'iworksie-speed-monitor' ),
				'why'   => __( 'Large stylesheets from themes and plugins delay the first paint, even though most of the rules are for other pages.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Use an optimisation plugin that removes unused CSS per page, or stop plugins loading their styles on pages where they are not used.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'minify'          => array(
				'title' => __( 'CSS or JavaScript files are not minified', 'iworksie-speed-monitor' ),
				'why'   => __( 'Unminified files contain spaces and comments that make them bigger than they need to be.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Turn on minification in your caching or optimisation plugin.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'images'          => array(
				'title' => __( 'Images are bigger than they need to be', 'iworksie-speed-monitor' ),
				'why'   => __( 'Large images are the most common reason pages are slow. Visitors download far more data than the screen can show.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Resize images before uploading, serve WebP or AVIF, and make sure your theme uses WordPress\'s responsive image sizes. An image optimisation plugin can convert existing images.', 'iworksie-speed-monitor' ),
				'who'   => 'content',
			),
			'lazy-load'       => array(
				'title' => __( 'Images below the fold load straight away', 'iworksie-speed-monitor' ),
				'why'   => __( 'Images nobody can see yet compete with the content at the top of the page.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Make sure images further down the page use lazy loading (WordPress adds it automatically unless a theme or plugin removes it).', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'server'          => array(
				'title' => __( 'Your server is slow to respond', 'iworksie-speed-monitor' ),
				'why'   => __( 'Nothing can start until the server sends the first byte of the page. A slow response delays everything else.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Turn on page caching, use a recent PHP version, and check for slow plugins. If it is still slow, your hosting plan may be too small or overloaded.', 'iworksie-speed-monitor' ),
				'who'   => 'hosting',
			),
			'redirects'       => array(
				'title' => __( 'The page redirects before loading', 'iworksie-speed-monitor' ),
				'why'   => __( 'Each redirect adds a full round trip before the page can start loading.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Link directly to the final address (for example https and with or without www, matching your WordPress Address setting).', 'iworksie-speed-monitor' ),
				'who'   => 'content',
			),
			'compression'     => array(
				'title' => __( 'Text files are sent uncompressed', 'iworksie-speed-monitor' ),
				'why'   => __( 'HTML, CSS and JavaScript compress very well. Sending them uncompressed wastes bandwidth.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Ask your host to enable gzip or Brotli compression, or enable it in your caching plugin.', 'iworksie-speed-monitor' ),
				'who'   => 'hosting',
			),
			'caching'         => array(
				'title' => __( 'Browser caching is too short', 'iworksie-speed-monitor' ),
				'why'   => __( 'Returning visitors download the same files again instead of reusing the copy they already have.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Set long cache lifetimes for images, CSS, JavaScript and fonts, through your caching plugin or your host.', 'iworksie-speed-monitor' ),
				'who'   => 'hosting',
			),
			'lcp'             => array(
				'title' => __( 'The main image or heading appears late', 'iworksie-speed-monitor' ),
				'why'   => __( 'Largest Contentful Paint is the moment visitors feel the page has loaded. It is one of Google\'s Core Web Vitals.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Do not lazy-load the main (hero) image, give it high priority, make it smaller, and avoid sliders or animations at the top of the page.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'layout-shift'    => array(
				'title' => __( 'The page jumps around while loading', 'iworksie-speed-monitor' ),
				'why'   => __( 'Content that moves as it loads makes people tap the wrong thing. Layout shift is one of Google\'s Core Web Vitals.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Give images, videos, embeds and ads a fixed width and height, and avoid banners or cookie notices that push content down after it appears.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'fonts'           => array(
				'title' => __( 'Text is hidden while web fonts load', 'iworksie-speed-monitor' ),
				'why'   => __( 'Visitors see blank space instead of text until custom fonts arrive.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Use font-display: swap, host fonts locally, and load fewer font weights.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'third-party'     => array(
				'title' => __( 'Third-party scripts are slowing the page', 'iworksie-speed-monitor' ),
				'why'   => __( 'Chat widgets, trackers, embeds and ad scripts run on other companies\' servers and can block your page.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Remove services you do not use, delay the rest until after the page loads, and replace heavy embeds (like videos) with a lightweight preview.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'js-work'         => array(
				'title' => __( 'Scripts keep the browser busy', 'iworksie-speed-monitor' ),
				'why'   => __( 'While JavaScript runs, the page cannot respond to taps and clicks. Phones feel this the most.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Reduce the number of plugins that add front-end scripts and delay non-essential scripts until the visitor interacts.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'dom-size'        => array(
				'title' => __( 'The page has a very large amount of HTML', 'iworksie-speed-monitor' ),
				'why'   => __( 'Thousands of elements take longer to lay out and make every interaction slower. Page builders often cause this.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Simplify the page layout, remove hidden sections, and paginate long lists.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'page-weight'     => array(
				'title' => __( 'The page is very heavy', 'iworksie-speed-monitor' ),
				'why'   => __( 'The total download is large, which is slow and costly on mobile data.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Start with the biggest files: usually images, videos, and large scripts.', 'iworksie-speed-monitor' ),
				'who'   => 'content',
			),
			'request-chain'   => array(
				'title' => __( 'Files load one after another', 'iworksie-speed-monitor' ),
				'why'   => __( 'When one file has to load before the browser discovers the next, the delays add up.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Preload key files (such as the main font or hero image), preconnect to important third-party domains, and reduce nested CSS imports.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
			'viewport'        => array(
				'title' => __( 'The page is not set up for mobile screens', 'iworksie-speed-monitor' ),
				'why'   => __( 'Without a proper viewport tag, phones add a delay to taps and show a zoomed-out page.', 'iworksie-speed-monitor' ),
				'fix'   => __( 'Make sure your theme outputs a viewport meta tag. Most modern themes do.', 'iworksie-speed-monitor' ),
				'who'   => 'code',
			),
		);
	}

	/**
	 * Topic for an audit ID, or the audit ID itself when unknown.
	 *
	 * @param string $audit_id Audit ID.
	 * @return string
	 */
	public static function topic_for( $audit_id ) {
		$map = self::audit_topics();
		return isset( $map[ $audit_id ] ) ? $map[ $audit_id ] : (string) $audit_id;
	}

	/**
	 * Advice for a stored opportunity row.
	 *
	 * @param array<string,mixed> $opportunity Row from the parser: id, topic, title, description.
	 * @return array{title: string, why: string, fix: string, who: string}
	 */
	public static function for_opportunity( array $opportunity ) {
		$topics = self::topics();
		$topic  = isset( $opportunity['topic'] ) ? (string) $opportunity['topic'] : '';
		if ( isset( $topics[ $topic ] ) ) {
			return $topics[ $topic ];
		}
		$title = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
		$desc  = isset( $opportunity['description'] ) ? (string) $opportunity['description'] : '';
		return array(
			'title' => '' !== $title ? $title : __( 'Performance issue', 'iworksie-speed-monitor' ),
			'why'   => self::strip_markdown( $desc ),
			'fix'   => '',
			'who'   => 'code',
		);
	}

	/**
	 * Strip Lighthouse's Markdown links and code ticks from a description.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function strip_markdown( $text ) {
		$text = (string) preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', (string) $text );
		$text = str_replace( '`', '', $text );
		$text = (string) preg_replace( '/\s*Learn more[^.]*\.?\s*$/i', '', $text );
		return trim( wp_strip_all_tags( $text ) );
	}
}

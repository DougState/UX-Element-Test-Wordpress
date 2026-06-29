#!/usr/bin/env node
/**
 * Standalone regression test for cross-page pageview goal matching.
 *
 * Executes the real frontend script in a minimal browser-like VM so the
 * conversion-only path exercises setupPageviewGoal(), normalizePageviewPath(),
 * cookie lookup, and sendTrackingRequest() together.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const frontendScript = fs.readFileSync(
	path.join( __dirname, '..', 'assets', 'js', 'frontend.js' ),
	'utf8'
);

class FormDataStub {
	constructor() {
		this.fields = [];
	}

	append( key, value ) {
		this.fields.push( [ key, String( value ) ] );
	}

	get( key ) {
		const found = this.fields.find( ( field ) => field[0] === key );
		return found ? found[1] : null;
	}
}

function runFrontendCase( options ) {
	const beacons = [];
	const search = options.search || '';
	const hash = options.hash || '';
	const href = 'https://example.test' + options.pathname + search + hash;
	const location = {
		protocol: 'https:',
		origin: 'https://example.test',
		pathname: options.pathname,
		search,
		hash,
		href
	};

	const document = {
		cookie: 'elementtest_variant_1=10',
		readyState: 'complete',
		documentElement: {},
		head: { appendChild() {} },
		body: { appendChild() {} },
		addEventListener() {},
		createElement() {
			return {
				setAttribute() {},
				style: {}
			};
		},
		getElementById() {
			return null;
		},
		getElementsByTagName() {
			return [ this.head ];
		},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		}
	};

	const window = {
		location,
		addEventListener() {},
		gtag: null
	};

	const sandbox = {
		console,
		document,
		window,
		navigator: {
			sendBeacon( url, formData ) {
				beacons.push( { url, formData } );
				return true;
			}
		},
		FormData: FormDataStub,
		URL,
		setTimeout() {},
		elementtestFrontend: {
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'nonce',
			tests: [],
			conversionOnlyTests: [
				{
					test_id: 1,
					variant_ids: [ 10 ],
					variants: [ { variant_id: 10, name: 'Variant' } ],
					goals: [
						{
							conversion_id: 2,
							revenue_value: 0,
							trigger_event: options.trigger
						}
					]
				}
			],
			cookieDays: 30,
			userHash: 'server-hash',
			ga4Enabled: false,
			homePath: options.homePath || ''
		}
	};

	vm.createContext( sandbox );
	vm.runInContext( frontendScript, sandbox, { filename: 'assets/js/frontend.js' } );

	return beacons;
}

function checkCase( label, options, expectedCount ) {
	const beacons = runFrontendCase( options );
	const actualCount = beacons.length;
	const conversionId = beacons[0] ? beacons[0].formData.get( 'conversion_id' ) : null;
	const passed = actualCount === expectedCount && ( 0 === expectedCount || conversionId === '2' );

	if ( passed ) {
		console.log( '  PASS: ' + label );
		return 0;
	}

	console.log( '  FAIL: ' + label );
	console.log( '        expected beacons: ' + expectedCount );
	console.log( '        actual beacons:   ' + actualCount );
	console.log( '        conversion_id:    ' + conversionId );
	return 1;
}

console.log( 'Pageview path normalization' );

let failures = 0;

failures += checkCase(
	'subdirectory install strips homePath from live browser path',
	{ homePath: '/blog', pathname: '/blog/thank-you', trigger: '/thank-you' },
	1
);

failures += checkCase(
	'subdirectory install also accepts prefixed trigger paths',
	{ homePath: '/blog', pathname: '/blog/thank-you', trigger: '/blog/thank-you' },
	1
);

failures += checkCase(
	'homePath stripping uses a path boundary',
	{ homePath: '/blog', pathname: '/blogging/thank-you', trigger: '/thank-you' },
	0
);

failures += checkCase(
	'path-only wildcard goals strip homePath before prefix matching',
	{ homePath: '/blog', pathname: '/blog/thank-you/order', trigger: '/thank-you/*' },
	1
);

failures += checkCase(
	'query-specific goals still require exact live URL match',
	{ homePath: '/blog', pathname: '/blog/thank-you', search: '?key=wrong', trigger: '/thank-you?key=right' },
	0
);

failures += checkCase(
	'root installs keep existing path-only matching',
	{ homePath: '', pathname: '/thank-you', trigger: '/thank-you' },
	1
);

console.log( '\n6 checks, ' + failures + ' failure(s)' );
process.exit( failures > 0 ? 1 : 0 );

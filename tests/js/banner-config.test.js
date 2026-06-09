import { describe, it, expect } from 'vitest';
import { coerceBannerConfig } from '../../assets/banner-config.js';

describe( 'banner-config parseCopy', () => {
	it( 'keeps a PHP-only copy key not present in the JS defaults', () => {
		const cfg = coerceBannerConfig( {
			copy: { doNotShare: 'Do Not Share', title: 'Custom title' },
		} );
		expect( cfg.copy.doNotShare ).toBe( 'Do Not Share' );
		expect( cfg.copy.title ).toBe( 'Custom title' );
		expect( cfg.copy.acceptAll ).toBe( 'Accept all' );
	} );
} );

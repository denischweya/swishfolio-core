/**
 * @wordpress/api-fetch stand-in.
 *
 * A bare jest mock: no test may reach the network, and the nonce middleware the
 * real module installs is WordPress's business, not this suite's.
 */

const apiFetch = jest.fn( () =>
	Promise.reject( new Error( 'apiFetch was not stubbed for this test' ) )
);

apiFetch.use = jest.fn();
apiFetch.setFetchHandler = jest.fn();

export default apiFetch;

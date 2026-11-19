/**
 * Swish AI settings screen behaviour.
 *
 * Keeps the model control honest: each provider offers a different model list,
 * so changing provider must repopulate it rather than leave a stale option that
 * the provider will reject.
 */
( function () {
	'use strict';

	var data = window.sfcoreSwishAi || { models: {}, i18n: {} };

	/**
	 * Replace a select's options with the models the given provider offers.
	 *
	 * @param {HTMLSelectElement} select   The model control.
	 * @param {string}            provider Provider slug.
	 * @param {string}            selected Currently stored model, if any.
	 */
	function populate( select, provider, selected ) {
		var models = data.models[ provider ] || {};
		var ids = Object.keys( models );

		select.innerHTML = '';

		if ( ! ids.length ) {
			// An OpenAI-compatible endpoint names its own models, so there is
			// nothing to enumerate — leave the field free text.
			return;
		}

		if ( ! selected ) {
			select.appendChild( option( '', data.i18n.chooseModel || '—' ) );
		}

		ids.forEach( function ( id ) {
			var el = option( id, models[ id ] );
			if ( id === selected ) {
				el.selected = true;
			}
			select.appendChild( el );
		} );
	}

	function option( value, label ) {
		var el = document.createElement( 'option' );
		el.value = value;
		el.textContent = label;
		return el;
	}

	/**
	 * A provider selector anywhere on the page repopulates its own card's models.
	 */
	function bindProviderSelectors() {
		var selectors = document.querySelectorAll( '[data-provider-select]' );

		Array.prototype.forEach.call( selectors, function ( selector ) {
			selector.addEventListener( 'change', function () {
				var target = document.querySelector(
					'.sfcore-ai-model[data-provider="' + selector.value + '"]'
				);

				if ( target && target.tagName === 'SELECT' ) {
					populate( target, selector.value, '' );
				}
			} );
		} );
	}

	/**
	 * Repopulate every card's model control from its provider's real list, so a
	 * model removed from a provider stops being offered after an update.
	 */
	function refreshCards() {
		var controls = document.querySelectorAll( 'select.sfcore-ai-model' );

		Array.prototype.forEach.call( controls, function ( control ) {
			var provider = control.getAttribute( 'data-provider' );
			var stored = ( data.settings
				&& data.settings.providers
				&& data.settings.providers[ provider ] )
				? data.settings.providers[ provider ].model
				: '';

			populate( control, provider, stored );
		} );
	}

	function bindDisconnectConfirm() {
		var buttons = document.querySelectorAll( '.sfcore-ai-disconnect' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( data.i18n.confirmReset || 'Disconnect?' ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	function bindCounters() {
		var fields = document.querySelectorAll( '[data-counter]' );

		Array.prototype.forEach.call( fields, function ( field ) {
			var counter = document.getElementById( field.getAttribute( 'data-counter' ) );
			if ( ! counter ) {
				return;
			}

			var limit = field.getAttribute( 'maxlength' ) || '';

			field.addEventListener( 'input', function () {
				counter.textContent = field.value.length + '/' + limit;
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		refreshCards();
		bindProviderSelectors();
		bindDisconnectConfirm();
		bindCounters();
	}
} )();

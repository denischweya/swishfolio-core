/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/compose"
/*!*********************************!*\
  !*** external ["wp","compose"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["compose"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/hooks"
/*!*******************************!*\
  !*** external ["wp","hooks"] ***!
  \*******************************/
(module) {

module.exports = window["wp"]["hooks"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!************************************!*\
  !*** ./src/js/button-extension.js ***!
  \************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Button Block Extension
 *
 * Adds to core/button:
 *  - Icon (before/after the link text)
 *  - Hover text + background color
 *  - Hover animation variant (lift / scale / slide / shift)
 *
 * Frontend rendering is handled server-side in ButtonExtension.php. This file
 * powers the editor: attribute registration, inspector controls, and a live
 * preview that mirrors what the front end will output.
 */








const TARGET_BLOCK = 'core/button';

// Icon catalog — slugs must match ButtonExtension.php's ICONS array.
const ICON_OPTIONS = [{
  value: '',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('— None —', 'swishfolio-core')
}, {
  value: 'arrow-right',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Arrow right', 'swishfolio-core')
}, {
  value: 'arrow-left',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Arrow left', 'swishfolio-core')
}, {
  value: 'arrow-up-right',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Arrow up-right (external)', 'swishfolio-core')
}, {
  value: 'arrow-down',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Arrow down (download)', 'swishfolio-core')
}, {
  value: 'chevron-right',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Chevron right', 'swishfolio-core')
}, {
  value: 'plus',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Plus', 'swishfolio-core')
}, {
  value: 'check',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Check', 'swishfolio-core')
}, {
  value: 'download',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Download', 'swishfolio-core')
}, {
  value: 'external',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('External', 'swishfolio-core')
}];
const ANIMATION_OPTIONS = [{
  value: '',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('None', 'swishfolio-core')
}, {
  value: 'lift',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Lift — translate up + shadow', 'swishfolio-core')
}, {
  value: 'scale',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Scale — slight grow', 'swishfolio-core')
}, {
  value: 'slide',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Slide — background fills', 'swishfolio-core')
}, {
  value: 'shift',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Shift — translate right', 'swishfolio-core')
}];

// Icon SVG strings are localised onto window.sfcoreButtonIcons by PHP.
const getIconSvg = slug => {
  if (!slug) {
    return '';
  }
  const lib = typeof window !== 'undefined' && window.sfcoreButtonIcons || {};
  return lib[slug] || '';
};

// 1. Register the new attributes on core/button.
function addButtonAttributes(settings, name) {
  if (name !== TARGET_BLOCK) {
    return settings;
  }
  return {
    ...settings,
    attributes: {
      ...settings.attributes,
      sfcoreIcon: {
        type: 'string',
        default: ''
      },
      sfcoreIconPosition: {
        type: 'string',
        default: 'after'
      },
      sfcoreHoverTextColor: {
        type: 'string',
        default: ''
      },
      sfcoreHoverBgColor: {
        type: 'string',
        default: ''
      },
      sfcoreHoverAnimation: {
        type: 'string',
        default: ''
      }
    }
  };
}
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('blocks.registerBlockType', 'sfcore/button-extension-attributes', addButtonAttributes);

// Small re-usable color swatch popover for hover colors.
const HoverColorControl = ({
  label,
  value,
  onChange
}) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.BaseControl, {
  __nextHasNoMarginBottom: true,
  label: label,
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Dropdown, {
    contentClassName: "sfcore-btn-color-popover",
    popoverProps: {
      placement: 'left-start',
      offset: 16
    },
    renderToggle: ({
      isOpen,
      onToggle
    }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
      variant: "secondary",
      onClick: onToggle,
      "aria-expanded": isOpen,
      className: "sfcore-btn-color-trigger",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
        className: "sfcore-btn-color-swatch",
        style: {
          background: value || 'transparent'
        }
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
        children: value || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Pick a color', 'swishfolio-core')
      }), value && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "tertiary",
        size: "small",
        onClick: e => {
          e.stopPropagation();
          onChange('');
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Clear', 'swishfolio-core')
      })]
    }),
    renderContent: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ColorPicker, {
      color: value,
      onChange: onChange,
      enableAlpha: true
    })
  })
});

// 2. Inspector controls + editor-side preview.
const withButtonControls = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_1__.createHigherOrderComponent)(BlockEdit => {
  return props => {
    if (props.name !== TARGET_BLOCK) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(BlockEdit, {
        ...props
      });
    }
    const {
      attributes,
      setAttributes
    } = props;
    const {
      sfcoreIcon = '',
      sfcoreIconPosition = 'after',
      sfcoreHoverTextColor = '',
      sfcoreHoverBgColor = '',
      sfcoreHoverAnimation = ''
    } = attributes;

    // Forward hover vars + animation class into the inline preview so the
    // editor reflects what the front end will render.
    const wrapperClass = [props.attributes.className || '', sfcoreIcon && 'sfcore-btn--has-icon', (sfcoreHoverTextColor || sfcoreHoverBgColor) && 'sfcore-btn--has-hover', sfcoreHoverAnimation && sfcoreHoverAnimation !== 'none' && `sfcore-btn--anim-${sfcoreHoverAnimation}`].filter(Boolean).join(' ');
    const previewStyle = {};
    if (sfcoreHoverTextColor) {
      previewStyle['--sfcore-btn-hover-text'] = sfcoreHoverTextColor;
    }
    if (sfcoreHoverBgColor) {
      previewStyle['--sfcore-btn-hover-bg'] = sfcoreHoverBgColor;
    }
    const forwardedProps = {
      ...props,
      attributes: {
        ...props.attributes,
        className: wrapperClass,
        style: {
          ...(props.attributes.style || {}),
          ...previewStyle
        }
      }
    };
    const iconSvg = getIconSvg(sfcoreIcon);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(BlockEdit, {
        ...forwardedProps
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Icon', 'swishfolio-core'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
            __nextHasNoMarginBottom: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Icon', 'swishfolio-core'),
            value: sfcoreIcon,
            options: ICON_OPTIONS,
            onChange: v => setAttributes({
              sfcoreIcon: v
            })
          }), sfcoreIcon && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
              __nextHasNoMarginBottom: true,
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Icon position', 'swishfolio-core'),
              value: sfcoreIconPosition,
              options: [{
                value: 'before',
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Before text', 'swishfolio-core')
              }, {
                value: 'after',
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('After text', 'swishfolio-core')
              }],
              onChange: v => setAttributes({
                sfcoreIconPosition: v
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelRow, {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                className: "sfcore-btn-icon-preview",
                "aria-hidden": "true",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.RawHTML, {
                  children: iconSvg
                })
              })
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover', 'swishfolio-core'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(HoverColorControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover text color', 'swishfolio-core'),
            value: sfcoreHoverTextColor,
            onChange: c => setAttributes({
              sfcoreHoverTextColor: c
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(HoverColorControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover background', 'swishfolio-core'),
            value: sfcoreHoverBgColor,
            onChange: c => setAttributes({
              sfcoreHoverBgColor: c
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
            __nextHasNoMarginBottom: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover animation', 'swishfolio-core'),
            value: sfcoreHoverAnimation,
            options: ANIMATION_OPTIONS,
            onChange: v => setAttributes({
              sfcoreHoverAnimation: v
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Animation plays when the visitor hovers the button. Frontend only.', 'swishfolio-core')
          })]
        })]
      })]
    });
  };
}, 'withButtonControls');
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('editor.BlockEdit', 'sfcore/button-extension-controls', withButtonControls);
})();

/******/ })()
;
//# sourceMappingURL=button-extension.js.map
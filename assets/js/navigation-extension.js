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
/*!****************************************!*\
  !*** ./src/js/navigation-extension.js ***!
  \****************************************/
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
 * Navigation Block Extension
 *
 * Adds to core/navigation:
 *  - Overlay alignment (left/center/right; overlay-only)
 *  - Hover style + hover color (applies in both inline and overlay modes)
 *  - Hamburger icon picker (preset library + custom SVG upload)
 *
 * Frontend rendering is handled server-side in NavigationExtension.php. This
 * file powers the editor: attribute registration and the InspectorControls.
 */








const TARGET_BLOCK = 'core/navigation';
const HAMBURGER_PRESETS = [{
  value: 'lines-3',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('3 lines (default)', 'swishfolio-core')
}, {
  value: 'lines-2',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('2 lines (minimal)', 'swishfolio-core')
}, {
  value: 'x',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('X', 'swishfolio-core')
}, {
  value: 'dots',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Dots', 'swishfolio-core')
}, {
  value: 'plus',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Plus', 'swishfolio-core')
}, {
  value: 'grid',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Grid (3×3)', 'swishfolio-core')
}];
const HOVER_STYLES = [{
  value: 'none',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('None', 'swishfolio-core')
}, {
  value: 'underline',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Underline', 'swishfolio-core')
}, {
  value: 'underline-grow',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Underline grow', 'swishfolio-core')
}, {
  value: 'color',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Color shift', 'swishfolio-core')
}];

// SVG library is localised onto window.sfcoreNavIcons by PHP.
const getPresetSvg = slug => {
  const lib = typeof window !== 'undefined' && window.sfcoreNavIcons || {};
  return lib[slug] || '';
};

// 1. Register new attributes on core/navigation.
function addNavAttributes(settings, name) {
  if (name !== TARGET_BLOCK) {
    return settings;
  }
  return {
    ...settings,
    attributes: {
      ...settings.attributes,
      sfcoreOverlayAlign: {
        type: 'string',
        default: ''
      },
      sfcoreHoverStyle: {
        type: 'string',
        default: 'none'
      },
      sfcoreHoverColor: {
        type: 'string',
        default: ''
      },
      sfcoreHamburgerIcon: {
        type: ['string', 'object'],
        default: ''
      }
    }
  };
}
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('blocks.registerBlockType', 'sfcore/navigation-extension-attributes', addNavAttributes);

// Reusable popover color picker (matches button-extension pattern).
const ColorPopover = ({
  label,
  value,
  onChange
}) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.BaseControl, {
  __nextHasNoMarginBottom: true,
  label: label,
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Dropdown, {
    contentClassName: "sfcore-nav-color-popover",
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
      className: "sfcore-nav-color-trigger",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
        className: "sfcore-nav-color-swatch",
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
const HamburgerPicker = ({
  value,
  onChange
}) => {
  const presetValue = typeof value === 'string' && value && value !== 'custom' ? value : '';
  const customUrl = value && typeof value === 'object' && value.type === 'custom' ? value.url : '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      __nextHasNoMarginBottom: true,
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hamburger icon', 'swishfolio-core'),
      value: presetValue,
      options: [{
        value: '',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('— Use core default —', 'swishfolio-core')
      }, ...HAMBURGER_PRESETS],
      onChange: v => onChange(v)
    }), presetValue && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "sfcore-nav-icon-preview",
      "aria-hidden": "true",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.RawHTML, {
        children: getPresetSvg(presetValue)
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.MediaUploadCheck, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.MediaUpload, {
        onSelect: media => onChange({
          type: 'custom',
          url: media.url
        }),
        allowedTypes: ['image/svg+xml'],
        render: ({
          open
        }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "secondary",
          onClick: open,
          style: {
            marginTop: 8
          },
          children: customUrl ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Replace custom SVG', 'swishfolio-core') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Upload custom SVG…', 'swishfolio-core')
        })
      })
    }), customUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        className: "sfcore-nav-icon-preview",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
          src: customUrl,
          alt: "",
          style: {
            width: 24,
            height: 24
          }
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "link",
        isDestructive: true,
        onClick: () => onChange(''),
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Remove custom SVG', 'swishfolio-core')
      })]
    })]
  });
};

// 2. Inspector controls + editor-side preview.
const withNavControls = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_1__.createHigherOrderComponent)(BlockEdit => {
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
      sfcoreOverlayAlign = '',
      sfcoreHoverStyle = 'none',
      sfcoreHoverColor = '',
      sfcoreHamburgerIcon = ''
    } = attributes;

    // Forward our class + inline CSS variable into the editor preview so
    // alignment / hover / color changes are visible without saving. Mirror
    // what NavigationExtension.php does at render_block time.
    const wrapperClass = [props.attributes.className || '', sfcoreOverlayAlign && `sfcore-nav--align-${sfcoreOverlayAlign}`, sfcoreHoverStyle && sfcoreHoverStyle !== 'none' && `sfcore-nav--hover-${sfcoreHoverStyle}`].filter(Boolean).join(' ');
    const previewStyle = {};
    if (sfcoreHoverColor) {
      previewStyle['--sfcore-nav-hover-color'] = sfcoreHoverColor;
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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(BlockEdit, {
        ...forwardedProps
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hamburger Style', 'swishfolio-core'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalToggleGroupControl, {
            __nextHasNoMarginBottom: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Overlay alignment', 'swishfolio-core'),
            value: sfcoreOverlayAlign || 'left',
            onChange: v => setAttributes({
              sfcoreOverlayAlign: v
            }),
            isBlock: true,
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Aligns items inside the mobile overlay only. Desktop alignment is set by the Layout panel above.', 'swishfolio-core'),
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalToggleGroupControlOption, {
              value: "left",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Left', 'swishfolio-core')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalToggleGroupControlOption, {
              value: "center",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Center', 'swishfolio-core')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalToggleGroupControlOption, {
              value: "right",
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Right', 'swishfolio-core')
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
            __nextHasNoMarginBottom: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover style', 'swishfolio-core'),
            value: sfcoreHoverStyle,
            options: HOVER_STYLES,
            onChange: v => setAttributes({
              sfcoreHoverStyle: v
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Applies to nav links in both inline (desktop) and overlay (mobile) modes.', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorPopover, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Hover color', 'swishfolio-core'),
            value: sfcoreHoverColor,
            onChange: c => setAttributes({
              sfcoreHoverColor: c
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(HamburgerPicker, {
            value: sfcoreHamburgerIcon,
            onChange: v => setAttributes({
              sfcoreHamburgerIcon: v
            })
          })]
        })
      })]
    });
  };
}, 'withNavControls');
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('editor.BlockEdit', 'sfcore/navigation-extension-controls', withNavControls);
})();

/******/ })()
;
//# sourceMappingURL=navigation-extension.js.map
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/bento-cards/block.json"
/*!***************************************!*\
  !*** ./blocks/bento-cards/block.json ***!
  \***************************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"swishfolio-core/bento-cards","version":"1.0.0","title":"Bento Cards","category":"swishfolio","description":"A bento-grid style card layout with images, titles, and CTAs for showcasing portfolio items or features.","keywords":["bento","cards","grid","portfolio","gallery","showcase"],"textdomain":"swishfolio-core","attributes":{"gridGap":{"type":"string","default":"1.5rem"},"cardStyle":{"type":"string","default":"overlay"},"cardOverlayColor":{"type":"string","default":""},"cardOverlayOpacity":{"type":"number","default":60},"accentColor":{"type":"string","default":""},"cardSubtitleColor":{"type":"string","default":""},"cardTitleColor":{"type":"string","default":""},"cardDescriptionColor":{"type":"string","default":""},"cardTextBg":{"type":"string","default":""},"cardTitleFontSize":{"type":"string","default":""},"layeredImages":{"type":"boolean","default":false},"layeredBgColor":{"type":"string","default":""},"layeredPaddingTop":{"type":"number","default":10},"layeredTallImage":{"type":"boolean","default":false},"layeredTallHeight":{"type":"number","default":395},"layeredTallSpeed":{"type":"number","default":4.6},"ctaType":{"type":"string","default":"none"},"ctaText":{"type":"string","default":"View All"},"ctaUrl":{"type":"string","default":""},"ctaTextColor":{"type":"string","default":""},"ctaBgColor":{"type":"string","default":""}},"supports":{"align":["wide","full"],"html":false,"color":{"background":true,"text":true},"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true}},"editorScript":"file:./build/index.js","editorStyle":"file:./build/index.css","style":"file:./build/style-index.css"}');

/***/ },

/***/ "./blocks/bento-cards/src/deprecated.js"
/*!**********************************************!*\
  !*** ./blocks/bento-cards/src/deprecated.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Bento Cards - Deprecated Versions.
 *
 * v1 stored each card as an object in a `cards` array attribute. v2 splits
 * cards into inner blocks (swishfolio-core/bento-card). The migrate below
 * converts old post content into inner blocks on first edit.
 */




const v1Attributes = {
  cards: {
    type: 'array',
    default: []
  },
  gridGap: {
    type: 'string',
    default: '1.5rem'
  },
  cardOverlayColor: {
    type: 'string',
    default: ''
  },
  cardOverlayOpacity: {
    type: 'number',
    default: 60
  },
  accentColor: {
    type: 'string',
    default: ''
  },
  ctaType: {
    type: 'string',
    default: 'none'
  },
  ctaText: {
    type: 'string',
    default: 'View All'
  },
  ctaUrl: {
    type: 'string',
    default: ''
  },
  ctaTextColor: {
    type: 'string',
    default: ''
  },
  ctaBgColor: {
    type: 'string',
    default: ''
  }
};
const v1Supports = {
  align: ['wide', 'full'],
  html: false,
  color: {
    background: true,
    text: true
  },
  spacing: {
    margin: true,
    padding: true
  },
  typography: {
    fontSize: true,
    lineHeight: true
  }
};
function v1Save({
  attributes
}) {
  const {
    cards,
    gridGap,
    cardOverlayColor,
    cardOverlayOpacity,
    accentColor,
    ctaType,
    ctaText,
    ctaUrl,
    ctaTextColor,
    ctaBgColor
  } = attributes;
  const getCardClasses = card => {
    const classes = ['sfcore-bento__card', `sfcore-bento__card--${card.cardSize || 'one-third'}`];
    if (card.image?.url) {
      classes.push('sfcore-bento__card--has-image');
    }
    return classes.join(' ');
  };
  const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps.save({
    className: 'sfcore-bento',
    style: {
      '--grid-gap': gridGap,
      '--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
      '--overlay-opacity': cardOverlayOpacity / 100,
      '--accent-color': accentColor || '#FFE500'
    }
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "sfcore-bento__grid",
      children: cards.map(card => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
        className: getCardClasses(card),
        children: [card.image?.url && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("img", {
          src: card.image.url,
          alt: card.image.alt || '',
          className: "sfcore-bento__card-image"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
          className: "sfcore-bento__card-overlay"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
          className: "sfcore-bento__card-content",
          children: [card.subtitle && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.RichText.Content, {
            tagName: "span",
            className: "sfcore-bento__card-subtitle",
            value: card.subtitle,
            style: card.titleColor ? {
              color: card.titleColor
            } : undefined
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
            className: "sfcore-bento__card-info",
            children: [card.title && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.RichText.Content, {
              tagName: "h3",
              className: "sfcore-bento__card-title",
              value: card.title,
              style: card.titleColor ? {
                color: card.titleColor
              } : undefined
            }), card.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.RichText.Content, {
              tagName: "p",
              className: "sfcore-bento__card-description",
              value: card.description,
              style: card.titleColor ? {
                color: card.titleColor
              } : undefined
            })]
          })]
        })]
      }, card.id))
    }), ctaType !== 'none' && ctaText && ctaUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "sfcore-bento__cta-wrapper",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("a", {
        href: ctaUrl,
        className: `sfcore-bento__cta sfcore-bento__cta--${ctaType}`,
        style: {
          color: ctaTextColor || undefined,
          backgroundColor: ctaType === 'button' ? ctaBgColor || undefined : undefined
        },
        children: ctaText
      })
    })]
  });
}
const v1 = {
  attributes: v1Attributes,
  supports: v1Supports,
  save: v1Save,
  isEligible: attributes => Array.isArray(attributes.cards) && attributes.cards.length > 0,
  migrate: attributes => {
    const {
      cards = [],
      ...rest
    } = attributes;
    const innerBlocks = cards.map(card => {
      const cardAttrs = {
        subtitle: card.subtitle || '',
        title: card.title || '',
        description: card.description || '',
        titleColor: card.titleColor || '',
        cardSize: card.cardSize || 'one-third'
      };
      if (card.image && card.image.url) {
        cardAttrs.image = card.image;
      }
      return (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.createBlock)('swishfolio-core/bento-card', cardAttrs);
    });
    return [rest, innerBlocks];
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ([v1]);

/***/ },

/***/ "./blocks/bento-cards/src/edit.js"
/*!****************************************!*\
  !*** ./blocks/bento-cards/src/edit.js ***!
  \****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _shared_components_inspector_tabs__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components/inspector-tabs */ "./blocks/shared/components/inspector-tabs.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Bento Cards - Edit Component.
 *
 * Parent layout block. Holds a grid of bento-card inner blocks. Emits CSS
 * custom properties on the wrapper (--grid-gap, --overlay-*, --accent-color,
 * --card-* text colours, --card-text-bg, --card-title-base-size,
 * --layered-bg-color, --layered-padding-top) used by both parent and card
 * styles.
 *
 * The grid-wide `cardStyle` attribute drives which visual style all cards
 * render — see style.scss for the five variants.
 */






const ALLOWED_BLOCKS = ['swishfolio-core/bento-card'];
const TEMPLATE = [['swishfolio-core/bento-card', {
  cardSize: 'full'
}], ['swishfolio-core/bento-card', {
  cardSize: 'two-thirds'
}], ['swishfolio-core/bento-card', {
  cardSize: 'one-third'
}]];
const STYLE_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay (default)', 'swishfolio-core'),
  value: 'overlay'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Hover Reveal', 'swishfolio-core'),
  value: 'hover-reveal'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Caption Below', 'swishfolio-core'),
  value: 'caption-below'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Stacked', 'swishfolio-core'),
  value: 'stacked'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('CTA', 'swishfolio-core'),
  value: 'cta'
}];

// Styles where the image doesn't fill the card — the only styles where the
// layered-image treatment makes visual sense.
const LAYERED_ELIGIBLE_STYLES = ['caption-below', 'stacked', 'cta'];

// Styles that surface a text container / band — where text-bg makes sense.
const TEXT_BG_STYLES = ['caption-below', 'stacked', 'cta'];
function Edit({
  attributes,
  setAttributes
}) {
  const [activeTab, setActiveTab] = (0,_shared_components_inspector_tabs__WEBPACK_IMPORTED_MODULE_3__.useInspectorTabs)();
  const themeColors = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useSetting)('color.palette') || [];
  const themeFontSizes = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useSetting)('typography.fontSizes') || [];
  const {
    gridGap,
    cardStyle,
    cardOverlayColor,
    cardOverlayOpacity,
    accentColor,
    cardSubtitleColor,
    cardTitleColor,
    cardDescriptionColor,
    cardTextBg,
    cardTitleFontSize,
    layeredImages,
    layeredBgColor,
    layeredPaddingTop,
    layeredTallImage,
    layeredTallHeight,
    layeredTallSpeed,
    ctaType,
    ctaText,
    ctaUrl,
    ctaTextColor,
    ctaBgColor
  } = attributes;
  const showLayeredToggle = LAYERED_ELIGIBLE_STYLES.includes(cardStyle);
  const showTextBg = TEXT_BG_STYLES.includes(cardStyle);
  const wrapperClasses = ['sfcore-bento', `sfcore-bento--style-${cardStyle || 'overlay'}`];
  if (layeredImages) {
    wrapperClasses.push('sfcore-bento--layered');
    if (layeredTallImage) {
      wrapperClasses.push('sfcore-bento--tall-image');
    }
  }
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: wrapperClasses.join(' '),
    style: {
      '--grid-gap': gridGap,
      '--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
      '--overlay-opacity': cardOverlayOpacity / 100,
      '--accent-color': accentColor || '#FFE500',
      '--card-subtitle-color': cardSubtitleColor || undefined,
      '--card-title-color': cardTitleColor || undefined,
      '--card-description-color': cardDescriptionColor || undefined,
      '--card-text-bg': cardTextBg || undefined,
      '--card-title-base-size': cardTitleFontSize || undefined,
      '--layered-bg-color': layeredBgColor || undefined,
      '--layered-padding-top': `${layeredPaddingTop !== null && layeredPaddingTop !== void 0 ? layeredPaddingTop : 10}%`,
      '--layered-tall-height': `${layeredTallHeight !== null && layeredTallHeight !== void 0 ? layeredTallHeight : 395}px`,
      '--layered-tall-speed': `${layeredTallSpeed !== null && layeredTallSpeed !== void 0 ? layeredTallSpeed : 4.6}s`
    }
  });
  const innerBlocksProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useInnerBlocksProps)({
    className: 'sfcore-bento__grid'
  }, {
    allowedBlocks: ALLOWED_BLOCKS,
    template: TEMPLATE,
    orientation: 'horizontal'
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components_inspector_tabs__WEBPACK_IMPORTED_MODULE_3__["default"], {
        activeTab: activeTab,
        setActiveTab: setActiveTab
      }), activeTab === 'general' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card Style', 'swishfolio-core'),
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Style', 'swishfolio-core'),
            value: cardStyle,
            options: STYLE_OPTIONS,
            onChange: value => setAttributes({
              cardStyle: value
            })
          }), showLayeredToggle && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              style: {
                marginTop: '16px',
                paddingTop: '12px',
                borderTop: '1px solid #ddd'
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Layered Images', 'swishfolio-core'),
                help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Display each image on a background color, bottom-aligned, at a configurable width.', 'swishfolio-core'),
                checked: !!layeredImages,
                onChange: value => setAttributes({
                  layeredImages: value
                })
              })
            }), layeredImages && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
                className: "components-base-control__label",
                style: {
                  marginTop: '12px'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Image Backdrop Color', 'swishfolio-core')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
                colors: themeColors,
                value: layeredBgColor,
                onChange: color => setAttributes({
                  layeredBgColor: color || ''
                }),
                clearable: true
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Padding Top (%)', 'swishfolio-core'),
                help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Extra breathing room above the image.', 'swishfolio-core'),
                value: layeredPaddingTop,
                onChange: value => setAttributes({
                  layeredPaddingTop: value !== null && value !== void 0 ? value : 10
                }),
                min: 0,
                max: 30,
                step: 1
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                style: {
                  marginTop: '16px',
                  paddingTop: '12px',
                  borderTop: '1px solid #eee'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tall Image (Hover to Scroll)', 'swishfolio-core'),
                  help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Allow taller images. Only the top is visible; hover reveals the rest with a scroll animation.', 'swishfolio-core'),
                  checked: !!layeredTallImage,
                  onChange: value => setAttributes({
                    layeredTallImage: value
                  })
                })
              }), layeredTallImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Viewport Height (px)', 'swishfolio-core'),
                  help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('How much of the image is visible before hover.', 'swishfolio-core'),
                  value: layeredTallHeight,
                  onChange: value => setAttributes({
                    layeredTallHeight: value !== null && value !== void 0 ? value : 395
                  }),
                  min: 200,
                  max: 800,
                  step: 5
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Scroll Speed (s)', 'swishfolio-core'),
                  help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duration of the hover reveal. Higher = slower scroll.', 'swishfolio-core'),
                  value: layeredTallSpeed,
                  onChange: value => setAttributes({
                    layeredTallSpeed: value !== null && value !== void 0 ? value : 4.6
                  }),
                  min: 0.5,
                  max: 10,
                  step: 0.1
                })]
              })]
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Layout', 'swishfolio-core'),
          initialOpen: false,
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Grid Gap', 'swishfolio-core'),
            value: gridGap,
            options: [{
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Small (1rem)', 'swishfolio-core'),
              value: '1rem'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Medium (1.5rem)', 'swishfolio-core'),
              value: '1.5rem'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Large (2rem)', 'swishfolio-core'),
              value: '2rem'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Extra Large (3rem)', 'swishfolio-core'),
              value: '3rem'
            }],
            onChange: value => setAttributes({
              gridGap: value
            })
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Section CTA', 'swishfolio-core'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('CTA Type', 'swishfolio-core'),
            value: ctaType,
            options: [{
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('None', 'swishfolio-core'),
              value: 'none'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Link', 'swishfolio-core'),
              value: 'link'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Button', 'swishfolio-core'),
              value: 'button'
            }],
            onChange: value => setAttributes({
              ctaType: value
            })
          }), ctaType !== 'none' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('CTA Text', 'swishfolio-core'),
              value: ctaText,
              onChange: value => setAttributes({
                ctaText: value
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.URLInput, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('CTA URL', 'swishfolio-core'),
              value: ctaUrl,
              onChange: url => setAttributes({
                ctaUrl: url
              })
            })]
          })]
        })]
      }), activeTab === 'style' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card Style Colours', 'swishfolio-core'),
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "components-base-control__label",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Accent Color', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
            colors: themeColors,
            value: accentColor,
            onChange: color => setAttributes({
              accentColor: color || ''
            }),
            clearable: true
          }), cardStyle === 'overlay' || cardStyle === 'hover-reveal' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
              className: "components-base-control__label",
              style: {
                marginTop: '16px'
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card Overlay Color', 'swishfolio-core')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
              colors: themeColors,
              value: cardOverlayColor,
              onChange: color => setAttributes({
                cardOverlayColor: color || ''
              }),
              clearable: true
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Opacity', 'swishfolio-core'),
              value: cardOverlayOpacity,
              onChange: value => setAttributes({
                cardOverlayOpacity: value
              }),
              min: 0,
              max: 100
            })]
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "components-base-control__label",
            style: {
              marginTop: '16px'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Subtitle Color', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
            colors: themeColors,
            value: cardSubtitleColor,
            onChange: color => setAttributes({
              cardSubtitleColor: color || ''
            }),
            clearable: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "components-base-control__label",
            style: {
              marginTop: '16px'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Title Color', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
            colors: themeColors,
            value: cardTitleColor,
            onChange: color => setAttributes({
              cardTitleColor: color || ''
            }),
            clearable: true
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "components-base-control__label",
            style: {
              marginTop: '16px'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Description Color', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
            colors: themeColors,
            value: cardDescriptionColor,
            onChange: color => setAttributes({
              cardDescriptionColor: color || ''
            }),
            clearable: true
          }), showTextBg && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
              className: "components-base-control__label",
              style: {
                marginTop: '16px'
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Text Background', 'swishfolio-core')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
              colors: themeColors,
              value: cardTextBg,
              onChange: color => setAttributes({
                cardTextBg: color || ''
              }),
              clearable: true
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card Typography', 'swishfolio-core'),
          initialOpen: false,
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FontSizePicker, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Title Base Size', 'swishfolio-core'),
            fontSizes: themeFontSizes,
            value: cardTitleFontSize,
            onChange: size => setAttributes({
              cardTitleFontSize: size || ''
            })
          })
        }), ctaType !== 'none' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
          title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Section CTA Colours', 'swishfolio-core'),
          initialOpen: false,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "components-base-control__label",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Text Color', 'swishfolio-core')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
            colors: themeColors,
            value: ctaTextColor,
            onChange: color => setAttributes({
              ctaTextColor: color || ''
            }),
            clearable: true
          }), ctaType === 'button' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
              className: "components-base-control__label",
              style: {
                marginTop: '12px'
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Background Color', 'swishfolio-core')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPalette, {
              colors: themeColors,
              value: ctaBgColor,
              onChange: color => setAttributes({
                ctaBgColor: color || ''
              }),
              clearable: true
            })]
          })]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      ...blockProps,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        ...innerBlocksProps
      }), ctaType !== 'none' && ctaText && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        className: "sfcore-bento__cta-wrapper",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          className: `sfcore-bento__cta sfcore-bento__cta--${ctaType}`,
          style: {
            color: ctaTextColor || undefined,
            backgroundColor: ctaType === 'button' ? ctaBgColor || undefined : undefined
          },
          children: ctaText
        })
      })]
    })]
  });
}

/***/ },

/***/ "./blocks/bento-cards/src/editor.scss"
/*!********************************************!*\
  !*** ./blocks/bento-cards/src/editor.scss ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./blocks/bento-cards/src/index.js"
/*!*****************************************!*\
  !*** ./blocks/bento-cards/src/index.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/grid.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../block.json */ "./blocks/bento-cards/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./blocks/bento-cards/src/edit.js");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./save */ "./blocks/bento-cards/src/save.js");
/* harmony import */ var _deprecated__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./deprecated */ "./blocks/bento-cards/src/deprecated.js");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "./blocks/bento-cards/src/style.scss");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./editor.scss */ "./blocks/bento-cards/src/editor.scss");
/**
 * Bento Cards Block
 *
 * A bento-grid style card layout for showcasing portfolio items.
 */









(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_4__["default"],
  deprecated: _deprecated__WEBPACK_IMPORTED_MODULE_5__["default"]
});

/***/ },

/***/ "./blocks/bento-cards/src/save.js"
/*!****************************************!*\
  !*** ./blocks/bento-cards/src/save.js ***!
  \****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ save)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Bento Cards - Save Component.
 *
 * Static save. Emits the wrapper (with grid-wide style + layered classes and
 * CSS custom properties) and the grid container with `<InnerBlocks.Content />`
 * for cards, plus the optional section CTA.
 */



function save({
  attributes
}) {
  const {
    gridGap,
    cardStyle,
    cardOverlayColor,
    cardOverlayOpacity,
    accentColor,
    cardSubtitleColor,
    cardTitleColor,
    cardDescriptionColor,
    cardTextBg,
    cardTitleFontSize,
    layeredImages,
    layeredBgColor,
    layeredPaddingTop,
    layeredTallImage,
    layeredTallHeight,
    layeredTallSpeed,
    ctaType,
    ctaText,
    ctaUrl,
    ctaTextColor,
    ctaBgColor
  } = attributes;
  const wrapperClasses = ['sfcore-bento', `sfcore-bento--style-${cardStyle || 'overlay'}`];
  if (layeredImages) {
    wrapperClasses.push('sfcore-bento--layered');
    if (layeredTallImage) {
      wrapperClasses.push('sfcore-bento--tall-image');
    }
  }
  const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps.save({
    className: wrapperClasses.join(' '),
    style: {
      '--grid-gap': gridGap,
      '--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
      '--overlay-opacity': cardOverlayOpacity / 100,
      '--accent-color': accentColor || '#FFE500',
      '--card-subtitle-color': cardSubtitleColor || undefined,
      '--card-title-color': cardTitleColor || undefined,
      '--card-description-color': cardDescriptionColor || undefined,
      '--card-text-bg': cardTextBg || undefined,
      '--card-title-base-size': cardTitleFontSize || undefined,
      '--layered-bg-color': layeredBgColor || undefined,
      '--layered-padding-top': `${layeredPaddingTop !== null && layeredPaddingTop !== void 0 ? layeredPaddingTop : 10}%`,
      '--layered-tall-height': `${layeredTallHeight !== null && layeredTallHeight !== void 0 ? layeredTallHeight : 395}px`,
      '--layered-tall-speed': `${layeredTallSpeed !== null && layeredTallSpeed !== void 0 ? layeredTallSpeed : 4.6}s`
    }
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      className: "sfcore-bento__grid",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InnerBlocks.Content, {})
    }), ctaType !== 'none' && ctaText && ctaUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
      className: "sfcore-bento__cta-wrapper",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("a", {
        href: ctaUrl,
        className: `sfcore-bento__cta sfcore-bento__cta--${ctaType}`,
        style: {
          color: ctaTextColor || undefined,
          backgroundColor: ctaType === 'button' ? ctaBgColor || undefined : undefined
        },
        children: ctaText
      })
    })]
  });
}

/***/ },

/***/ "./blocks/bento-cards/src/style.scss"
/*!*******************************************!*\
  !*** ./blocks/bento-cards/src/style.scss ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./blocks/shared/components/inspector-tabs.js"
/*!****************************************************!*\
  !*** ./blocks/shared/components/inspector-tabs.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ InspectorTabs),
/* harmony export */   useInspectorTabs: () => (/* binding */ useInspectorTabs)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/settings.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/styles.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/tool.js");
/* harmony import */ var _inspector_tabs_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./inspector-tabs.scss */ "./blocks/shared/components/inspector-tabs.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Shared InspectorTabs component.
 *
 * Renders a General / Style / Advanced tab switcher at the top of the block
 * inspector (modelled on the Kadence Blocks pattern). Each block keeps the
 * active tab in local state via the `useInspectorTabs` hook and conditionally
 * renders its PanelBody groups per tab.
 */







const TAB_CONFIG = {
  general: {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('General', 'swishfolio-core'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"]
  },
  style: {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Style', 'swishfolio-core'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"]
  },
  advanced: {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Advanced', 'swishfolio-core'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"]
  }
};

/**
 * Tab state hook. Mirrors Kadence: plain local state, defaults to "general",
 * does not persist across reloads.
 *
 * @param {string} initial Initial active tab name.
 * @return {[string, Function]} [activeTab, setActiveTab]
 */
function useInspectorTabs(initial = 'general') {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(initial);
}
function InspectorTabs({
  activeTab,
  setActiveTab,
  allowedTabs = ['general', 'style', 'advanced']
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
    className: "sfcore-inspector-tabs",
    children: allowedTabs.map(name => {
      const tab = TAB_CONFIG[name];
      if (!tab) {
        return null;
      }
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        className: `sfcore-inspector-tabs__tab ${activeTab === name ? 'is-active' : ''}`,
        onClick: () => setActiveTab(name),
        "aria-pressed": activeTab === name,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
          icon: tab.icon
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
          children: tab.title
        })]
      }, name);
    })
  });
}

/***/ },

/***/ "./blocks/shared/components/inspector-tabs.scss"
/*!******************************************************!*\
  !*** ./blocks/shared/components/inspector-tabs.scss ***!
  \******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/grid.js"
/*!********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/grid.js ***!
  \********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


const grid = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24",
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    d: "m3 5c0-1.10457.89543-2 2-2h13.5c1.1046 0 2 .89543 2 2v13.5c0 1.1046-.8954 2-2 2h-13.5c-1.10457 0-2-.8954-2-2zm2-.5h6v6.5h-6.5v-6c0-.27614.22386-.5.5-.5zm-.5 8v6c0 .2761.22386.5.5.5h6v-6.5zm8 0v6.5h6c.2761 0 .5-.2239.5-.5v-6zm0-8v6.5h6.5v-6c0-.27614-.2239-.5-.5-.5z",
    fillRule: "evenodd",
    clipRule: "evenodd"
  })
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (grid);
//# sourceMappingURL=grid.js.map

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/settings.js"
/*!************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/settings.js ***!
  \************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


const settings = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24",
  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    d: "m19 7.5h-7.628c-.3089-.87389-1.1423-1.5-2.122-1.5-.97966 0-1.81309.62611-2.12197 1.5h-2.12803v1.5h2.12803c.30888.87389 1.14231 1.5 2.12197 1.5.9797 0 1.8131-.62611 2.122-1.5h7.628z"
  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    d: "m19 15h-2.128c-.3089-.8739-1.1423-1.5-2.122-1.5s-1.8131.6261-2.122 1.5h-7.628v1.5h7.628c.3089.8739 1.1423 1.5 2.122 1.5s1.8131-.6261 2.122-1.5h2.128z"
  })]
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (settings);
//# sourceMappingURL=settings.js.map

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/styles.js"
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/styles.js ***!
  \**********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__),
/* harmony export */   styles: () => (/* binding */ styles)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


const styles = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, {
  viewBox: "0 0 24 24",
  xmlns: "http://www.w3.org/2000/svg",
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    fillRule: "evenodd",
    clipRule: "evenodd",
    d: "M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 0 1-6.5 6.5v-13a6.5 6.5 0 0 1 6.5 6.5Z"
  })
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (styles);
//# sourceMappingURL=styles.js.map

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/tool.js"
/*!********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/tool.js ***!
  \********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


const tool = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24",
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    d: "M14.103 7.128l2.26-2.26a4 4 0 00-5.207 4.804L5.828 15a2 2 0 102.828 2.828l5.329-5.328a4 4 0 004.804-5.208l-2.261 2.26-1.912-.512-.513-1.912zm-7.214 9.64a.5.5 0 11.707-.707.5.5 0 01-.707.707z"
  })
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (tool);
//# sourceMappingURL=tool.js.map

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/blocks"
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
(module) {

module.exports = window["wp"]["blocks"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"bento-cards/build/index": 0,
/******/ 			"bento-cards/build/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkswishfolio_core"] = globalThis["webpackChunkswishfolio_core"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["bento-cards/build/style-index"], () => (__webpack_require__("./blocks/bento-cards/src/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map
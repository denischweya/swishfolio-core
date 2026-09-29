/**
 * @wordpress/editor stand-in.
 *
 * The real `PluginSidebar` renders its children only while its complementary
 * area is the active one; here it always renders them, because the point of the
 * component tests is the body, not the complementary-area plumbing. The `name`
 * and `title` land on the DOM so a test can still assert the sidebar is the one
 * registered under SIDEBAR_NAME.
 */

export const store = { name: 'core/editor' };

export const PluginSidebar = ( { children, name, title, className } ) => (
	<div className={ className } data-sidebar-name={ name }>
		<h2>{ title }</h2>
		{ children }
	</div>
);

export const PluginSidebarMoreMenuItem = ( { children, target } ) => (
	<div className="interface-more-menu-item" data-target={ target }>
		{ children }
	</div>
);

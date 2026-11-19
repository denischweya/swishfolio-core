/**
 * One sentence, with the machine detail behind a disclosure.
 *
 * The headline never varies by failure mode: whether the provider was
 * unreachable, the response failed validation, or the hourly limit was hit, the
 * user's situation is identical — nothing changed.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ErrorPanel = ( { error } ) => (
	<Notice
		className="sfcore-ai-sidebar__error"
		status="error"
		isDismissible={ false }
	>
		<p className="sfcore-ai-sidebar__error-message">{ error.message }</p>

		{ error.hint && (
			<p className="sfcore-ai-sidebar__error-hint">{ error.hint }</p>
		) }

		{ error.detail && (
			<details className="sfcore-ai-sidebar__error-details">
				<summary>{ __( 'Details', 'swishfolio-core' ) }</summary>
				<pre>{ error.detail }</pre>
			</details>
		) }
	</Notice>
);

export default ErrorPanel;

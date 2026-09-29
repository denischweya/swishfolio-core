/**
 * The prompt field and its suggestion chips.
 */

import { Button, Spinner, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { MAX_INSTRUCTION_LENGTH } from '../ai/constants';
import { getSuggestions } from '../ai/suggestions';

const PromptForm = ( {
	capability,
	instruction,
	setInstruction,
	onSubmit,
	isWorking,
} ) => {
	const suggestions = getSuggestions( capability );
	const canSubmit = instruction.trim() !== '' && ! isWorking;

	const handleKeyDown = ( event ) => {
		if (
			( event.metaKey || event.ctrlKey ) &&
			event.key === 'Enter' &&
			canSubmit
		) {
			event.preventDefault();
			onSubmit();
		}
	};

	return (
		<div className="sfcore-ai-sidebar__prompt">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'What should change?', 'swishfolio-core' ) }
				help={ __(
					'Describe the change in plain language. Nothing is applied until you review it.',
					'swishfolio-core'
				) }
				value={ instruction }
				onChange={ ( value ) =>
					setInstruction( value.slice( 0, MAX_INSTRUCTION_LENGTH ) )
				}
				onKeyDown={ handleKeyDown }
				rows={ 4 }
				disabled={ isWorking }
			/>

			{ suggestions.length > 0 && (
				<div className="sfcore-ai-sidebar__chips">
					<span className="sfcore-ai-sidebar__chips-label">
						{ __( 'Try', 'swishfolio-core' ) }
					</span>
					{ suggestions.map( ( suggestion ) => (
						<Button
							key={ suggestion.id }
							className="sfcore-ai-sidebar__chip"
							variant="secondary"
							size="small"
							disabled={ isWorking }
							onClick={ () =>
								setInstruction( suggestion.prompt )
							}
						>
							{ suggestion.label }
						</Button>
					) ) }
				</div>
			) }

			<div className="sfcore-ai-sidebar__prompt-actions">
				<Button
					variant="primary"
					onClick={ onSubmit }
					disabled={ ! canSubmit }
					isBusy={ isWorking }
				>
					{ isWorking
						? __( 'Asking AI…', 'swishfolio-core' )
						: __( 'Ask AI', 'swishfolio-core' ) }
				</Button>
				{ isWorking && <Spinner /> }
			</div>
		</div>
	);
};

export default PromptForm;

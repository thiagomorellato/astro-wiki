<?php

namespace MediaWiki\Extension\CodeEditor;

use MediaWiki\Config\Config;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\BetaFeatures\BetaFeatures;
use MediaWiki\Extension\CodeEditor\Hooks\HookRunner;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\EditPage__showReadOnlyForm_initialHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;

/**
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	GetPreferencesHook,
	EditPage__showEditForm_initialHook,
	EditPage__showReadOnlyForm_initialHook
{
	private UserOptionsLookup $userOptionsLookup;
	private HookRunner $hookRunner;
	private array $enabledContentModels;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		HookContainer $hookContainer,
		Config $config
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->enabledContentModels = $config->get( 'CodeEditorContentModels' );
	}

	private function getPageLanguage( Title $title, string $model, string $format ): ?string {
		if ( $model === CONTENT_MODEL_JAVASCRIPT ) {
			return 'javascript';
		} elseif ( $model === CONTENT_MODEL_CSS ) {
			return 'css';
		} elseif ( $model === CONTENT_MODEL_JSON ) {
			return 'json';
		}

		// Give extensions a chance
		$lang = null;
		$this->hookRunner->onCodeEditorGetPageLanguage( $title, $lang, $model, $format );

		return $lang;
	}

	/**
	 * @param User $user
	 * @param array &$defaultPreferences
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['usecodeeditor'] = [
			'type' => 'api',
			'default' => '1',
		];
	}

	/**
	 * @param EditPage $editpage
	 * @param OutputPage $output
	 * @throws ErrorPageError
	 */
	public function onEditPage__showEditForm_initial( $editpage, $output ) {
		$model = $editpage->contentModel;
		if ( ( $this->enabledContentModels[ $model ] ?? true ) === false || (
				// TODO: Remove after CodeMirror is out of Beta
				ExtensionRegistry::getInstance()->isLoaded( 'BetaFeatures' ) &&
				BetaFeatures::isFeatureEnabled( $output->getUser(), 'codemirror-beta-feature-enable' )
			)
		) {
			return;
		}

		$title = $editpage->getContextTitle();
		$format = $editpage->contentFormat;

		$lang = $this->getPageLanguage( $title, $model, $format );
		if ( $lang && $this->userOptionsLookup->getOption( $output->getUser(), 'usebetatoolbar' ) ) {
			$output->addModules( 'ext.codeEditor' );
			$output->addModuleStyles( 'ext.codeEditor.styles' );
			$output->addJsConfigVars( 'wgCodeEditorCurrentLanguage', $lang );
			// Needed because ACE adds a blob: url web-worker.
			$output->getCSP()->addScriptSrc( 'blob:' );

			if ( $this->userOptionsLookup->getOption( $output->getUser(), 'usecodeeditor' ) ) {
				$output->addBodyClasses( 'codeeditor-loading' );
			}
		} elseif ( !ExtensionRegistry::getInstance()->isLoaded( 'WikiEditor' ) ) {
			throw new ErrorPageError( 'codeeditor-error-title', 'codeeditor-error-message' );
		}
	}

	/**
	 * @param EditPage $editpage
	 * @param OutputPage $output
	 * @throws ErrorPageError
	 */
	public function onEditPage__showReadOnlyForm_initial( $editpage, $output ) {
		$this->onEditPage__showEditForm_initial( $editpage, $output );
	}
}

<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks;

use MediaWiki\Content\Content;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\RCVariableGenerator;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * Handle running AbuseFilter's hooks
 * @author DannyS712
 */
class AbuseFilterHookRunner implements
	AbuseFilterAlterVariablesHook,
	AbuseFilterBuilderHook,
	AbuseFilterCanViewProtectedVariablesHook,
	AbuseFilterComputeVariableHook,
	AbuseFilterContentToStringHook,
	AbuseFilterCustomActionsHook,
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterDeprecatedVariablesHook,
	AbuseFilterFilterActionHook,
	AbuseFilterGenerateGenericVarsHook,
	AbuseFilterGenerateTitleVarsHook,
	AbuseFilterGenerateUserVarsHook,
	AbuseFilterGenerateVarsForRecentChangeHook,
	AbuseFilterInterceptVariableHook,
	AbuseFilterProtectedVarsAccessLoggerHook,
	AbuseFilterShouldFilterActionHook,
	AbuseFilterGetDangerousActionsHook
{
	public const SERVICE_NAME = 'AbuseFilterHookRunner';

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_builder( array &$realValues ) {
		return $this->hookContainer->run(
			'AbuseFilter-builder',
			[ &$realValues ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_deprecatedVariables( array &$deprecatedVariables ) {
		return $this->hookContainer->run(
			'AbuseFilter-deprecatedVariables',
			[ &$deprecatedVariables ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_computeVariable(
		string $method,
		VariableHolder $vars,
		array $parameters,
		?string &$result
	) {
		return $this->hookContainer->run(
			'AbuseFilter-computeVariable',
			[ $method, $vars, $parameters, &$result ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_contentToString(
		Content $content,
		?string &$text
	) {
		return $this->hookContainer->run(
			'AbuseFilter-contentToString',
			[ $content, &$text ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_filterAction(
		VariableHolder &$vars,
		Title $title
	) {
		return $this->hookContainer->run(
			'AbuseFilter-filterAction',
			[ &$vars, $title ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterAlterVariables(
		VariableHolder &$vars,
		Title $title,
		User $user
	) {
		return $this->hookContainer->run(
			'AbuseFilterAlterVariables',
			[ &$vars, $title, $user ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_generateTitleVars(
		VariableHolder $vars,
		Title $title,
		string $prefix,
		?RecentChange $rc
	) {
		return $this->hookContainer->run(
			'AbuseFilter-generateTitleVars',
			[ $vars, $title, $prefix, $rc ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_generateUserVars(
		VariableHolder $vars,
		User $user,
		?RecentChange $rc
	) {
		return $this->hookContainer->run(
			'AbuseFilter-generateUserVars',
			[ $vars, $user, $rc ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_generateGenericVars(
		VariableHolder $vars,
		?RecentChange $rc
	) {
		return $this->hookContainer->run(
			'AbuseFilter-generateGenericVars',
			[ $vars, $rc ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterGenerateVarsForRecentChange(
		RCVariableGenerator $generator,
		RecentChange $rc,
		VariableHolder $vars,
		User $contextUser
	) {
		return $this->hookContainer->run(
			'AbuseFilterGenerateVarsForRecentChange',
			[ $generator, $rc, $vars, $contextUser ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilter_interceptVariable(
		string $method,
		VariableHolder $vars,
		array $parameters,
		&$result
	) {
		return $this->hookContainer->run(
			'AbuseFilter-interceptVariable',
			[ $method, $vars, $parameters, &$result ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterShouldFilterAction(
		VariableHolder $vars,
		Title $title,
		User $user,
		array &$skipReasons
	) {
		return $this->hookContainer->run(
			'AbuseFilterShouldFilterAction',
			[ $vars, $title, $user, &$skipReasons ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterGetDangerousActions( array &$actions ): void {
		$this->hookContainer->run(
			'AbuseFilterGetDangerousActions',
			[ &$actions ],
			[ 'abortable' => false ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterCustomActions( array &$actions ): void {
		$this->hookContainer->run(
			'AbuseFilterCustomActions',
			[ &$actions ],
			[ 'abortable' => false ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterCustomProtectedVariables( array &$variables ): void {
		$this->hookContainer->run(
			'AbuseFilterCustomProtectedVariables',
			[ &$variables ],
			[ 'abortable' => false ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onAbuseFilterLogProtectedVariableValueAccess(
		UserIdentity $performer,
		string $target,
		string $action,
		bool $shouldDebounce,
		int $timestamp,
		array $params
	) {
		return $this->hookContainer->run(
			'AbuseFilterLogProtectedVariableValueAccess',
			[ $performer, $target, $action, $shouldDebounce, $timestamp, $params ],
			[ 'abortable' => true ]
		);
	}

	/** @inheritDoc */
	public function onAbuseFilterCanViewProtectedVariables(
		Authority $performer, array $variables, AbuseFilterPermissionStatus $status
	): void {
		$this->hookContainer->run(
			'AbuseFilterCanViewProtectedVariables',
			[ $performer, $variables, $status ],
			[ 'abortable' => false ]
		);
	}
}

<?php

namespace MediaWiki\Extension\AbuseFilter\VariableGenerator;

use LogicException;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MWFileProps;
use Wikimedia\Mime\MimeAnalyzer;

/**
 * This class contains the logic used to create variable holders used to
 * examine a RecentChanges row.
 */
class RCVariableGenerator extends VariableGenerator {
	/**
	 * @var RecentChange
	 */
	private $rc;

	/** @var User */
	private $contextUser;

	/** @var MimeAnalyzer */
	private $mimeAnalyzer;
	/** @var RepoGroup */
	private $repoGroup;
	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param UserFactory $userFactory
	 * @param MimeAnalyzer $mimeAnalyzer
	 * @param RepoGroup $repoGroup
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RecentChange $rc
	 * @param User $contextUser
	 * @param VariableHolder|null $vars
	 */
	public function __construct(
		AbuseFilterHookRunner $hookRunner,
		UserFactory $userFactory,
		MimeAnalyzer $mimeAnalyzer,
		RepoGroup $repoGroup,
		WikiPageFactory $wikiPageFactory,
		RecentChange $rc,
		User $contextUser,
		?VariableHolder $vars = null
	) {
		parent::__construct( $hookRunner, $userFactory, $vars );

		$this->mimeAnalyzer = $mimeAnalyzer;
		$this->repoGroup = $repoGroup;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->rc = $rc;
		$this->contextUser = $contextUser;
	}

	/**
	 * @return VariableHolder|null
	 */
	public function getVars(): ?VariableHolder {
		if ( $this->rc->getAttribute( 'rc_source' ) === RecentChange::SRC_LOG ) {
			switch ( $this->rc->getAttribute( 'rc_log_type' ) ) {
				case 'move':
					$this->addMoveVars();
					break;
				case 'newusers':
					$this->addCreateAccountVars();
					break;
				case 'delete':
					$this->addDeleteVars();
					break;
				case 'upload':
					$this->addUploadVars();
					break;
				default:
					return null;
			}
		} elseif ( $this->rc->getAttribute( 'rc_this_oldid' ) ) {
			// It's an edit (or a page creation).
			$this->addEditVarsForRow();
		} elseif (
			!$this->hookRunner->onAbuseFilterGenerateVarsForRecentChange(
				$this, $this->rc, $this->vars, $this->contextUser )
		) {
			// @codeCoverageIgnoreStart
			throw new LogicException( 'Cannot understand the given recentchanges row!' );
			// @codeCoverageIgnoreEnd
		}

		$this->addGenericVars( $this->rc );

		return $this->vars;
	}

	/**
	 * @return $this
	 */
	private function addMoveVars(): self {
		$userIdentity = $this->rc->getPerformerIdentity();

		$oldTitle = Title::castFromPageReference( $this->rc->getPage() ) ?: Title::makeTitle( NS_SPECIAL, 'BadTitle' );
		$newTitle = Title::newFromText( $this->rc->getParam( '4::target' ) );

		$this->addUserVars( $userIdentity, $this->rc )
			->addTitleVars( $oldTitle, 'moved_from', $this->rc )
			->addTitleVars( $newTitle, 'moved_to', $this->rc );

		$this->vars->setVar( 'summary', $this->rc->getAttribute( 'rc_comment' ) );
		$this->vars->setVar( 'action', 'move' );

		$this->vars->setLazyLoadVar(
			'moved_from_last_edit_age',
			'revision-age',
			[
				// rc_last_oldid is zero (RecentChange::newLogEntry)
				'revid' => $this->rc->getAttribute( 'rc_this_oldid' ),
				'parent' => true,
				'asof' => $this->rc->getAttribute( 'rc_timestamp' ),
			]
		);
		// TODO: add moved_to_last_edit_age (is it possible?)
		// TODO: add old_wikitext etc. (T320347)

		return $this;
	}

	/**
	 * @return $this
	 */
	private function addCreateAccountVars(): self {
		$this->vars->setVar(
			'action',
			// XXX: as of 1.43, the following is never true
			$this->rc->getAttribute( 'rc_log_action' ) === 'autocreate'
				? 'autocreateaccount'
				: 'createaccount'
		);

		$name = Title::castFromPageReference( $this->rc->getPage() )->getText();
		// Add user data if the account was created by a registered user
		$userIdentity = $this->rc->getPerformerIdentity();
		if ( $userIdentity->isRegistered() && $name !== $userIdentity->getName() ) {
			$this->addUserVars( $userIdentity, $this->rc );
		} else {
			// Set the user_type so that creations of temporary accounts vs named accounts can be filtered for an
			// abuse filter that matches account creations.
			$this->vars->setLazyLoadVar(
				'user_type',
				'user-type',
				[ 'user-identity' => $userIdentity ]
			);
		}

		$this->vars->setVar( 'accountname', $name );

		return $this;
	}

	/**
	 * @return $this
	 */
	private function addDeleteVars(): self {
		$title = Title::castFromPageReference( $this->rc->getPage() ) ?: Title::makeTitle( NS_SPECIAL, 'BadTitle' );
		$userIdentity = $this->rc->getPerformerIdentity();

		$this->addUserVars( $userIdentity, $this->rc )
			->addTitleVars( $title, 'page', $this->rc );

		$this->vars->setVar( 'action', 'delete' );
		$this->vars->setVar( 'summary', $this->rc->getAttribute( 'rc_comment' ) );
		// TODO: add page_last_edit_age
		// TODO: add old_wikitext etc. (T173663)

		return $this;
	}

	/**
	 * @return $this
	 */
	private function addUploadVars(): self {
		$title = Title::castFromPageReference( $this->rc->getPage() ) ?: Title::makeTitle( NS_SPECIAL, 'BadTitle' );
		$userIdentity = $this->rc->getPerformerIdentity();

		$this->addUserVars( $userIdentity, $this->rc )
			->addTitleVars( $title, 'page', $this->rc );

		$this->vars->setVar( 'action', 'upload' );
		$this->vars->setVar( 'summary', $this->rc->getAttribute( 'rc_comment' ) );

		$this->vars->setLazyLoadVar(
			'page_last_edit_age',
			'revision-age',
			[
				// rc_last_oldid is zero (RecentChange::newLogEntry)
				'revid' => $this->rc->getAttribute( 'rc_this_oldid' ),
				'parent' => true,
				'asof' => $this->rc->getAttribute( 'rc_timestamp' ),
			]
		);

		$time = $this->rc->getParam( 'img_timestamp' );
		$file = $this->repoGroup->findFile(
			$title, [ 'time' => $time, 'private' => $this->contextUser ]
		);
		if ( !$file ) {
			// @fixme Ensure this cannot happen!
			// @codeCoverageIgnoreStart
			$logger = LoggerFactory::getInstance( 'AbuseFilter' );
			$logger->warning( "Cannot find file from RC row with title $title" );
			return $this;
			// @codeCoverageIgnoreEnd
		}

		// This is the same as FilteredActionsHandler::filterUpload, but from a different source
		$this->vars->setVar( 'file_sha1', \Wikimedia\base_convert( $file->getSha1(), 36, 16, 40 ) );
		$this->vars->setVar( 'file_size', $file->getSize() );

		$this->vars->setVar( 'file_mime', $file->getMimeType() );
		$this->vars->setVar(
			'file_mediatype',
			$this->mimeAnalyzer->getMediaType( null, $file->getMimeType() )
		);
		$this->vars->setVar( 'file_width', $file->getWidth() );
		$this->vars->setVar( 'file_height', $file->getHeight() );

		$mwProps = new MWFileProps( $this->mimeAnalyzer );
		$bits = $mwProps->getPropsFromPath( $file->getLocalRefPath(), true )['bits'];
		$this->vars->setVar( 'file_bits_per_channel', $bits );

		$this->vars->setLazyLoadVar( 'new_wikitext', 'revision-text',
			[ 'revid' => $this->rc->getAttribute( 'rc_this_oldid' ), 'contextUser' => $this->contextUser ] );
		$this->vars->setLazyLoadVar( 'old_wikitext', 'revision-text',
			[
				// rc_last_oldid is zero (RecentChange::newLogEntry)
				'revid' => $this->rc->getAttribute( 'rc_this_oldid' ),
				'parent' => true,
				'contextUser' => $this->contextUser,
			] );

		$this->addEditVars(
			$this->wikiPageFactory->newFromTitle( $title ),
			$this->contextUser,
			false
		);

		return $this;
	}

	/**
	 * @return $this
	 */
	private function addEditVarsForRow(): self {
		$title = Title::castFromPageReference( $this->rc->getPage() ) ?: Title::makeTitle( NS_SPECIAL, 'BadTitle' );
		$userIdentity = $this->rc->getPerformerIdentity();

		$this->addUserVars( $userIdentity, $this->rc )
			->addTitleVars( $title, 'page', $this->rc );

		$this->vars->setVar( 'action', 'edit' );
		$this->vars->setVar( 'summary', $this->rc->getAttribute( 'rc_comment' ) );

		$this->vars->setLazyLoadVar( 'new_wikitext', 'revision-text',
			[ 'revid' => $this->rc->getAttribute( 'rc_this_oldid' ), 'contextUser' => $this->contextUser ] );
		$this->vars->setLazyLoadVar( 'new_content_model', 'content-model',
			[ 'revid' => $this->rc->getAttribute( 'rc_this_oldid' ) ] );

		$parentId = $this->rc->getAttribute( 'rc_last_oldid' );
		if ( $parentId ) {
			$this->vars->setLazyLoadVar( 'old_wikitext', 'revision-text',
				[ 'revid' => $parentId, 'contextUser' => $this->contextUser ] );
			$this->vars->setLazyLoadVar( 'old_content_model', 'content-model',
				[ 'revid' => $parentId ] );
			$this->vars->setLazyLoadVar( 'page_last_edit_age', 'revision-age',
				[ 'revid' => $parentId, 'asof' => $this->rc->getAttribute( 'rc_timestamp' ) ] );
		} else {
			$this->vars->setVar( 'old_wikitext', '' );
			$this->vars->setVar( 'old_content_model', '' );
			$this->vars->setVar( 'page_last_edit_age', null );
		}

		$this->addEditVars(
			$this->wikiPageFactory->newFromTitle( $title ),
			$this->contextUser,
			false
		);

		return $this;
	}
}

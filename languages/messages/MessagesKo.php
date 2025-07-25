<?php
/** Korean (한국어)
 *
 * @file
 * @ingroup Languages
 *
 * @author Albamhandae
 * @author Altostratus
 * @author Bluehill395
 * @author Chanhee
 * @author ChongDae
 * @author Chulki Lee
 * @author Clockoon
 * @author Cwt96
 * @author Devunt
 * @author Ficell
 * @author Freebiekr
 * @author Gapo
 * @author Gjue
 * @author Ha98574
 * @author Hoo
 * @author IRTC1015
 * @author ITurtle
 * @author Idh0854
 * @author Jmkim dot com
 * @author Jskang
 * @author Kaganer
 * @author Klutzy
 * @author Kwj2772
 * @author LFM
 * @author Leehoy
 * @author lens0021
 * @author Mintz0223
 * @author Pi.C.Noizecehx
 * @author Priviet
 * @author PuzzletChung
 * @author Revi
 * @author TheAlpha for knowledge
 * @author ToePeu
 * @author Yjs5497
 * @author Yknok29
 * @author לערי ריינהארט
 * @author 관인생략
 * @author 아라
 */

$namespaceNames = [
	NS_MEDIA            => '미디어',
	NS_SPECIAL          => '특수',
	NS_TALK             => '토론',
	NS_USER             => '사용자',
	NS_USER_TALK        => '사용자토론',
	NS_PROJECT_TALK     => '$1토론',
	NS_FILE             => '파일',
	NS_FILE_TALK        => '파일토론',
	NS_MEDIAWIKI        => '미디어위키',
	NS_MEDIAWIKI_TALK   => '미디어위키토론',
	NS_TEMPLATE         => '틀',
	NS_TEMPLATE_TALK    => '틀토론',
	NS_HELP             => '도움말',
	NS_HELP_TALK        => '도움말토론',
	NS_CATEGORY         => '분류',
	NS_CATEGORY_TALK    => '분류토론',
];

$namespaceAliases = [
	'특' => NS_SPECIAL,
	'특수기능' => NS_SPECIAL,
	'MediaWiki토론' => NS_MEDIAWIKI_TALK,
	'그림' => NS_FILE,
	'그림토론' => NS_FILE_TALK,
];

/** @phpcs-require-sorted-array */
$specialPageAliases = [
	'Activeusers'                 => [ '활동적인사용자' ],
	'Allmessages'                 => [ '모든메시지' ],
	'AllMyUploads'                => [ '모든내올린파일', '모든내파일' ],
	'Allpages'                    => [ '모든문서' ],
	'Ancientpages'                => [ '오래된문서' ],
	'ApiHelp'                     => [ 'Api도움말' ],
	'ApiSandbox'                  => [ 'Api연습장' ],
	'AuthenticationPopupSuccess'  => [ '인증팝업성공' ],
	'AutoblockList'               => [ '자동차단목록' ],
	'Badtitle'                    => [ '잘못된제목', '인식불가제목', '잘못된이름', '인식불가이름' ],
	'Blankpage'                   => [ '빈문서' ],
	'Block'                       => [ '차단', 'IP차단', '사용자차단' ],
	'BlockList'                   => [ '차단목록', 'IP차단목록', '차단된사용자' ],
	'Booksources'                 => [ '책찾기' ],
	'BotPasswords'                => [ '봇비밀번호' ],
	'BrokenRedirects'             => [ '끊긴넘겨주기' ],
	'Categories'                  => [ '분류' ],
	'ChangeContentModel'          => [ '콘텐츠모델바꾸기', '콘텐츠모델변경' ],
	'ChangeCredentials'           => [ '자격증명바꾸기', '자격증명변경' ],
	'ChangeEmail'                 => [ '이메일바꾸기', '이메일변경' ],
	'ChangePassword'              => [ '비밀번호바꾸기', '비밀번호변경' ],
	'ComparePages'                => [ '문서비교' ],
	'Confirmemail'                => [ '이메일확인', '이메일인증' ],
	'Contributions'               => [ '기여', '기여목록' ],
	'CreateAccount'               => [ '계정만들기', '가입' ],
	'Deadendpages'                => [ '막다른문서' ],
	'DeletedContributions'        => [ '삭제된기여' ],
	'DeletePage'                  => [ '문서삭제', '삭제' ],
	'Diff'                        => [ '차이' ],
	'DoubleRedirects'             => [ '이중넘겨주기' ],
	'EditPage'                    => [ '문서편집', '편집' ],
	'EditRecovery'                => [ '편집복구' ],
	'EditTags'                    => [ '태그편집' ],
	'EditWatchlist'               => [ '주시문서목록편집' ],
	'Emailuser'                   => [ '이메일보내기', '이메일' ],
	'ExpandTemplates'             => [ '틀전개' ],
	'Export'                      => [ '내보내기' ],
	'Fewestrevisions'             => [ '역사짧은문서' ],
	'FileDuplicateSearch'         => [ '중복파일검색', '중복파일찾기' ],
	'Filepath'                    => [ '파일경로', '그림경로' ],
	'GoToInterwiki'               => [ '인터위키가기' ],
	'Import'                      => [ '가져오기' ],
	'Interwiki'                   => [ '인터위키' ],
	'Invalidateemail'             => [ '이메일인증취소', '이메일인증해제' ],
	'JavaScriptTest'              => [ '자바스크립트시험', '자바스크립트테스트' ],
	'LinkAccounts'                => [ '계정연결' ],
	'LinkSearch'                  => [ '링크검색', '링크찾기' ],
	'Listadmins'                  => [ '관리자목록', '관리자' ],
	'Listbots'                    => [ '봇목록', '봇' ],
	'ListDuplicatedFiles'         => [ '중복된파일목록' ],
	'Listfiles'                   => [ '파일목록', '그림목록', '파일', '그림' ],
	'Listgrants'                  => [ '권한부여목록' ],
	'Listgrouprights'             => [ '사용자권한목록', '사용자권한', '권한목록' ],
	'Listredirects'               => [ '넘겨주기목록' ],
	'Listusers'                   => [ '사용자목록', '사용자' ],
	'Lockdb'                      => [ 'DB잠금', 'DB잠그기' ],
	'Log'                         => [ '기록', '로그' ],
	'Lonelypages'                 => [ '외톨이문서', '홀로된문서' ],
	'Longpages'                   => [ '긴문서' ],
	'MediaStatistics'             => [ '미디어통계' ],
	'MergeHistory'                => [ '역사합치기' ],
	'MIMEsearch'                  => [ 'MIME검색', 'MIME찾기' ],
	'Mostcategories'              => [ '많이분류된문서' ],
	'Mostimages'                  => [ '많이쓰는파일', '많이쓰는그림' ],
	'Mostinterwikis'              => [ '인터위키많은문서' ],
	'Mostlinked'                  => [ '많이링크된문서' ],
	'Mostlinkedcategories'        => [ '많이쓰는분류' ],
	'Mostlinkedtemplates'         => [ '많이쓰는틀' ],
	'Mostrevisions'               => [ '역사긴문서' ],
	'Movepage'                    => [ '이동', '문서이동', '옮기기', '문서옮기기' ],
	'Mute'                        => [ '뮤트' ],
	'Mycontributions'             => [ '내기여', '내기여목록' ],
	'MyLanguage'                  => [ '내언어' ],
	'Mylog'                       => [ '내기록' ],
	'Mypage'                      => [ '내사용자문서' ],
	'Mytalk'                      => [ '내사용자토론' ],
	'Myuploads'                   => [ '내가올린파일' ],
	'NamespaceInfo'               => [ '이름공간정보' ],
	'Newimages'                   => [ '새파일', '새그림' ],
	'Newpages'                    => [ '새문서' ],
	'NewSection'                  => [ '새문단' ],
	'PageData'                    => [ '문서데이터' ],
	'PageHistory'                 => [ '문서역사', '역사' ],
	'PageInfo'                    => [ '문서정보', '정보' ],
	'PageLanguage'                => [ '문서언어' ],
	'PagesWithProp'               => [ '속성별문서' ],
	'PasswordPolicies'            => [ '비밀번호정책' ],
	'PasswordReset'               => [ '비밀번호재설정', '비밀번호초기화' ],
	'PermanentLink'               => [ '고유링크', '영구링크' ],
	'Preferences'                 => [ '환경설정' ],
	'Prefixindex'                 => [ '접두어찾기' ],
	'Protectedpages'              => [ '보호된문서' ],
	'Protectedtitles'             => [ '생성보호된문서', '만들기보호된문서' ],
	'ProtectPage'                 => [ '문서보호', '보호' ],
	'Purge'                       => [ '새로고침' ],
	'RandomInCategory'            => [ '분류안의임의문서' ],
	'Randompage'                  => [ '임의문서' ],
	'Randomredirect'              => [ '임의넘겨주기' ],
	'Randomrootpage'              => [ '임의최상위문서', '임의루트문서' ],
	'Recentchanges'               => [ '최근바뀜' ],
	'Recentchangeslinked'         => [ '링크최근바뀜' ],
	'Redirect'                    => [ '넘겨주기' ],
	'RemoveCredentials'           => [ '자격증명삭제', '자격증명제거' ],
	'Renameuser'                  => [ '사용자이름바꾸기', '이름바꾸기', '계정이름바꾸기' ],
	'ResetTokens'                 => [ '토큰재설정' ],
	'Revisiondelete'              => [ '특정판삭제' ],
	'RunJobs'                     => [ '작업실행' ],
	'Search'                      => [ '검색', '찾기' ],
	'Shortpages'                  => [ '짧은문서' ],
	'Specialpages'                => [ '특수문서', '특수기능' ],
	'Statistics'                  => [ '통계' ],
	'Tags'                        => [ '태그' ],
	'TalkPage'                    => [ '토론문서' ],
	'TrackingCategories'          => [ '추적용분류' ],
	'Unblock'                     => [ '차단해제' ],
	'Uncategorizedcategories'     => [ '분류안된분류' ],
	'Uncategorizedimages'         => [ '분류안된파일', '분류안된그림' ],
	'Uncategorizedpages'          => [ '분류안된문서' ],
	'Uncategorizedtemplates'      => [ '분류안된틀' ],
	'Undelete'                    => [ '삭제취소', '삭제된문서' ],
	'UnlinkAccounts'              => [ '계정연결해제' ],
	'Unlockdb'                    => [ 'DB잠금해제', 'DB잠금취소' ],
	'Unusedcategories'            => [ '안쓰는분류', '쓰이지않는분류' ],
	'Unusedimages'                => [ '안쓰는파일', '안쓰는그림', '쓰이지않는파일', '쓰이지않는그림' ],
	'Unusedtemplates'             => [ '안쓰는틀', '쓰이지않는틀' ],
	'Unwatchedpages'              => [ '주시안되는문서' ],
	'Upload'                      => [ '올리기', '파일올리기', '그림올리기', '업로드' ],
	'UploadStash'                 => [ '올린비공개파일', '비공개로올린파일' ],
	'Userlogin'                   => [ '로그인', '사용자로그인' ],
	'Userlogout'                  => [ '로그아웃', '사용자로그아웃' ],
	'Userrights'                  => [ '권한조정', '관리자하기', '봇하기' ],
	'Version'                     => [ '버전' ],
	'Wantedcategories'            => [ '필요한분류' ],
	'Wantedfiles'                 => [ '필요한파일', '필요한그림' ],
	'Wantedpages'                 => [ '필요한문서' ],
	'Wantedtemplates'             => [ '필요한틀' ],
	'Watchlist'                   => [ '주시문서목록', '주시목록' ],
	'Whatlinkshere'               => [ '가리키는문서', '링크하는문서' ],
	'Withoutinterwiki'            => [ '인터위키없는문서' ],
];

/** @phpcs-require-sorted-array */
$magicWords = [
	'anchorencode'              => [ '0', '책갈피인코딩', 'ANCHORENCODE' ],
	'articlepath'               => [ '0', '항목경로', '기사경로', 'ARTICLEPATH' ],
	'basepagename'              => [ '1', '상위문서이름', 'BASEPAGENAME' ],
	'basepagenamee'             => [ '1', '상위문서이름E', 'BASEPAGENAMEE' ],
	'canonicalurl'              => [ '0', '표준주소:', 'CANONICALURL:' ],
	'canonicalurle'             => [ '0', '표준주소E:', 'CANONICALURLE:' ],
	'cascadingsources'          => [ '1', '연쇄식원본', '계단식원본', 'CASCADINGSOURCES' ],
	'contentlanguage'           => [ '1', '기본언어', 'CONTENTLANGUAGE', 'CONTENTLANG' ],
	'currentday'                => [ '1', '현재일', 'CURRENTDAY' ],
	'currentday2'               => [ '1', '현재일2', 'CURRENTDAY2' ],
	'currentdayname'            => [ '1', '현재요일', 'CURRENTDAYNAME' ],
	'currentdow'                => [ '1', '현재요일숫자', 'CURRENTDOW' ],
	'currenthour'               => [ '1', '현재시', 'CURRENTHOUR' ],
	'currentmonth'              => [ '1', '현재월', 'CURRENTMONTH', 'CURRENTMONTH2' ],
	'currentmonth1'             => [ '1', '현재월1', 'CURRENTMONTH1' ],
	'currentmonthabbrev'        => [ '1', '현재월이름약자', 'CURRENTMONTHABBREV' ],
	'currentmonthname'          => [ '1', '현재월이름', 'CURRENTMONTHNAME' ],
	'currentmonthnamegen'       => [ '1', '현재월이름소유격', 'CURRENTMONTHNAMEGEN' ],
	'currenttime'               => [ '1', '현재시각', '현재시분', 'CURRENTTIME' ],
	'currenttimestamp'          => [ '1', '현재타임스탬프', 'CURRENTTIMESTAMP' ],
	'currentversion'            => [ '1', '현재버전', 'CURRENTVERSION' ],
	'currentweek'               => [ '1', '현재주', 'CURRENTWEEK' ],
	'currentyear'               => [ '1', '현재년', 'CURRENTYEAR' ],
	'defaultsort'               => [ '1', '기본정렬:', 'DEFAULTSORT:', 'DEFAULTSORTKEY:', 'DEFAULTCATEGORYSORT:' ],
	'defaultsort_noerror'       => [ '0', '오류없음', 'noerror' ],
	'defaultsort_noreplace'     => [ '0', '바꾸기없음', 'noreplace' ],
	'directionmark'             => [ '1', '명령검토', 'DIRECTIONMARK', 'DIRMARK' ],
	'displaytitle'              => [ '1', '보일제목', '표시제목', 'DISPLAYTITLE' ],
	'filepath'                  => [ '0', '파일경로:', '그림경로:', 'FILEPATH:' ],
	'forcetoc'                  => [ '0', '__목차보임__', '__목차표시__', '__FORCETOC__' ],
	'formatdate'                => [ '0', '날짜형식', 'formatdate', 'dateformat' ],
	'formatnum'                 => [ '0', '수형식', 'FORMATNUM' ],
	'fullpagename'              => [ '1', '전체문서이름', 'FULLPAGENAME' ],
	'fullpagenamee'             => [ '1', '전체문서이름E', 'FULLPAGENAMEE' ],
	'fullurl'                   => [ '0', '전체주소:', 'FULLURL:' ],
	'fullurle'                  => [ '0', '전체주소E:', 'FULLURLE:' ],
	'gender'                    => [ '0', '성별:', 'GENDER:' ],
	'grammar'                   => [ '0', '문법:', 'GRAMMAR:' ],
	'hiddencat'                 => [ '1', '__숨은분류__', '__HIDDENCAT__' ],
	'img_alt'                   => [ '1', '대체글=$1', 'alt=$1' ],
	'img_baseline'              => [ '1', '밑줄', 'baseline' ],
	'img_border'                => [ '1', '테두리', 'border' ],
	'img_bottom'                => [ '1', '아래', 'bottom' ],
	'img_center'                => [ '1', '가운데', 'center', 'centre' ],
	'img_class'                 => [ '1', '클래스=$1', 'class=$1' ],
	'img_framed'                => [ '1', '프레임', 'frame', 'framed', 'enframed' ],
	'img_frameless'             => [ '1', '프레임없음', 'frameless' ],
	'img_lang'                  => [ '1', '언어=$1', 'lang=$1' ],
	'img_left'                  => [ '1', '왼쪽', 'left' ],
	'img_link'                  => [ '1', '링크=$1', 'link=$1' ],
	'img_manualthumb'           => [ '1', '섬네일=$1', '썸네일=$1', '축소판=$1', 'thumbnail=$1', 'thumb=$1' ],
	'img_middle'                => [ '1', '중간', 'middle' ],
	'img_none'                  => [ '1', '없음', 'none' ],
	'img_page'                  => [ '1', '문서=$1', 'page=$1', 'page $1' ],
	'img_right'                 => [ '1', '오른쪽', 'right' ],
	'img_sub'                   => [ '1', '아래첨자', 'sub' ],
	'img_super'                 => [ '1', '위첨자', 'super', 'sup' ],
	'img_text_bottom'           => [ '1', '글자아래', '텍스트아래', 'text-bottom' ],
	'img_text_top'              => [ '1', '글자위', '텍스트위', 'text-top' ],
	'img_thumbnail'             => [ '1', '섬네일', '썸네일', '축소판', 'thumb', 'thumbnail' ],
	'img_top'                   => [ '1', '위', 'top' ],
	'img_upright'               => [ '1', '위오른쪽', '위오른쪽=$1', 'upright', 'upright=$1', 'upright $1' ],
	'img_width'                 => [ '1', '$1픽셀', '$1px' ],
	'index'                     => [ '1', '__색인__', '__INDEX__' ],
	'int'                       => [ '0', '인터페이스:', 'INT:' ],
	'language'                  => [ '0', '#언어', '#LANGUAGE' ],
	'lc'                        => [ '0', '소문자:', 'LC:' ],
	'lcfirst'                   => [ '0', '첫소문자:', 'LCFIRST:' ],
	'localday'                  => [ '1', '지역일', 'LOCALDAY' ],
	'localday2'                 => [ '1', '지역일2', 'LOCALDAY2' ],
	'localdayname'              => [ '1', '지역요일', 'LOCALDAYNAME' ],
	'localdow'                  => [ '1', '지역요일숫자', 'LOCALDOW' ],
	'localhour'                 => [ '1', '지역시', 'LOCALHOUR' ],
	'localmonth'                => [ '1', '지역월', 'LOCALMONTH', 'LOCALMONTH2' ],
	'localmonth1'               => [ '1', '지역월1', 'LOCALMONTH1' ],
	'localmonthabbrev'          => [ '1', '지역월이름약자', 'LOCALMONTHABBREV' ],
	'localmonthname'            => [ '1', '지역월이름', 'LOCALMONTHNAME' ],
	'localmonthnamegen'         => [ '1', '지역월이름소유격', 'LOCALMONTHNAMEGEN' ],
	'localtime'                 => [ '1', '지역시분', '지역시각', 'LOCALTIME' ],
	'localtimestamp'            => [ '1', '지역타임스탬프', 'LOCALTIMESTAMP' ],
	'localurl'                  => [ '0', '지역주소:', 'LOCALURL:' ],
	'localurle'                 => [ '0', '지역주소E:', 'LOCALURLE:' ],
	'localweek'                 => [ '1', '지역주', 'LOCALWEEK' ],
	'localyear'                 => [ '1', '지역년', 'LOCALYEAR' ],
	'msg'                       => [ '0', '메시지:', 'MSG:' ],
	'msgnw'                     => [ '0', '위키잘못메시지:', 'MSGNW:' ],
	'namespace'                 => [ '1', '이름공간', 'NAMESPACE' ],
	'namespacee'                => [ '1', '이름공간E', 'NAMESPACEE' ],
	'namespacenumber'           => [ '1', '이름공간수', 'NAMESPACENUMBER' ],
	'newsectionlink'            => [ '1', '__새문단쓰기__', '__새글쓰기__', '__NEWSECTIONLINK__' ],
	'nocommafysuffix'           => [ '0', '구분자없음', 'NOSEP' ],
	'nocontentconvert'          => [ '0', '__내용변환없음__', '__내변없음__', '__내용변환안함__', '__내변안함__', '__NOCONTENTCONVERT__', '__NOCC__' ],
	'noeditsection'             => [ '0', '__부분편집숨김__', '__문단편집숨김__', '__단락편집숨김__', '__NOEDITSECTION__' ],
	'nogallery'                 => [ '0', '__갤러리숨김__', '__화랑숨김__', '__NOGALLERY__' ],
	'noindex'                   => [ '1', '__색인안함__', '__색인거부__', '__NOINDEX__' ],
	'nonewsectionlink'          => [ '1', '__새문단쓰기숨기기__', '__새글쓰기숨기기__', '__NONEWSECTIONLINK__' ],
	'notitleconvert'            => [ '0', '__제목변환없음__', '__제변없음__', '__제목변환안함__', '__제변안함__', '__NOTITLECONVERT__', '__NOTC__' ],
	'notoc'                     => [ '0', '__목차숨김__', '__NOTOC__' ],
	'ns'                        => [ '0', '이름:', '이름공간:', 'NS:' ],
	'nse'                       => [ '0', '이름E:', '이름공간E:', 'NSE:' ],
	'numberingroup'             => [ '1', '권한별사용자수', '그룹별사용자수', 'NUMBERINGROUP', 'NUMINGROUP' ],
	'numberofactiveusers'       => [ '1', '활동중인사용자수', 'NUMBEROFACTIVEUSERS' ],
	'numberofadmins'            => [ '1', '관리자수', 'NUMBEROFADMINS' ],
	'numberofarticles'          => [ '1', '문서수', 'NUMBEROFARTICLES' ],
	'numberofedits'             => [ '1', '편집수', 'NUMBEROFEDITS' ],
	'numberoffiles'             => [ '1', '파일수', '그림수', 'NUMBEROFFILES' ],
	'numberofpages'             => [ '1', '모든문서수', 'NUMBEROFPAGES' ],
	'numberofusers'             => [ '1', '사용자수', '계정수', 'NUMBEROFUSERS' ],
	'padleft'                   => [ '0', '대체왼쪽', 'PADLEFT' ],
	'padright'                  => [ '0', '대체오른쪽', 'PADRIGHT' ],
	'pageid'                    => [ '0', '문서번호', 'PAGEID' ],
	'pagename'                  => [ '1', '문서이름', 'PAGENAME' ],
	'pagenamee'                 => [ '1', '문서이름E', 'PAGENAMEE' ],
	'pagesincategory'           => [ '1', '분류문서수', 'PAGESINCATEGORY', 'PAGESINCAT' ],
	'pagesincategory_all'       => [ '0', '모두', 'all' ],
	'pagesincategory_files'     => [ '0', '파일', 'files' ],
	'pagesincategory_pages'     => [ '0', '문서', 'pages' ],
	'pagesincategory_subcats'   => [ '0', '하위분류', 'subcats' ],
	'pagesinnamespace'          => [ '1', '이름공간문서수', 'PAGESINNAMESPACE:', 'PAGESINNS:' ],
	'pagesize'                  => [ '1', '문서크기', 'PAGESIZE' ],
	'plural'                    => [ '0', '복수:', '복수형:', 'PLURAL:' ],
	'protectionlevel'           => [ '1', '보호수준', 'PROTECTIONLEVEL' ],
	'raw'                       => [ '0', '원본:', 'RAW:' ],
	'rawsuffix'                 => [ '1', '원', 'R' ],
	'redirect'                  => [ '0', '#넘겨주기', '#REDIRECT' ],
	'revisionday'               => [ '1', '판일', 'REVISIONDAY' ],
	'revisionday2'              => [ '1', '판일2', 'REVISIONDAY2' ],
	'revisionid'                => [ '1', '판번호', 'REVISIONID' ],
	'revisionmonth'             => [ '1', '판월', 'REVISIONMONTH' ],
	'revisionmonth1'            => [ '1', '판월1', 'REVISIONMONTH1' ],
	'revisionsize'              => [ '1', '판크기', 'REVISIONSIZE' ],
	'revisiontimestamp'         => [ '1', '판타임스탬프', 'REVISIONTIMESTAMP' ],
	'revisionuser'              => [ '1', '판사용자', 'REVISIONUSER' ],
	'revisionyear'              => [ '1', '판년', 'REVISIONYEAR' ],
	'rootpagename'              => [ '1', '최상위문서이름', 'ROOTPAGENAME' ],
	'rootpagenamee'             => [ '1', '최상위문서이름E', 'ROOTPAGENAMEE' ],
	'safesubst'                 => [ '0', '안전풀기:', 'SAFESUBST:' ],
	'scriptpath'                => [ '0', '스크립트경로', 'SCRIPTPATH' ],
	'server'                    => [ '0', '서버', 'SERVER' ],
	'servername'                => [ '0', '서버이름', 'SERVERNAME' ],
	'sitename'                  => [ '1', '사이트이름', 'SITENAME' ],
	'special'                   => [ '0', '특수', '특수기능', 'special' ],
	'speciale'                  => [ '0', '특수E', '특수기능E', '특수기능e', 'speciale' ],
	'staticredirect'            => [ '1', '__넘겨주기고정__', '__STATICREDIRECT__' ],
	'stylepath'                 => [ '0', '스타일경로', 'STYLEPATH' ],
	'subjectpagename'           => [ '1', '본문서이름', 'SUBJECTPAGENAME', 'ARTICLEPAGENAME' ],
	'subjectpagenamee'          => [ '1', '본문서이름E', 'SUBJECTPAGENAMEE', 'ARTICLEPAGENAMEE' ],
	'subjectspace'              => [ '1', '본문서이름공간', 'SUBJECTSPACE', 'ARTICLESPACE' ],
	'subjectspacee'             => [ '1', '본문서이름공간E', 'SUBJECTSPACEE', 'ARTICLESPACEE' ],
	'subpagename'               => [ '1', '하위문서이름', 'SUBPAGENAME' ],
	'subpagenamee'              => [ '1', '하위문서이름E', 'SUBPAGENAMEE' ],
	'subst'                     => [ '0', '풀기:', 'SUBST:' ],
	'tag'                       => [ '0', '태그', 'tag' ],
	'talkpagename'              => [ '1', '토론문서이름', 'TALKPAGENAME' ],
	'talkpagenamee'             => [ '1', '토론문서이름E', 'TALKPAGENAMEE' ],
	'talkspace'                 => [ '1', '토론이름공간', 'TALKSPACE' ],
	'talkspacee'                => [ '1', '토론이름공간E', 'TALKSPACEE' ],
	'toc'                       => [ '0', '__목차__', '__TOC__' ],
	'uc'                        => [ '0', '대문자:', 'UC:' ],
	'ucfirst'                   => [ '0', '첫대문자:', 'UCFIRST:' ],
	'urlencode'                 => [ '0', '주소인코딩:', 'URLENCODE:' ],
	'url_path'                  => [ '0', '경로', 'PATH' ],
	'url_query'                 => [ '0', '쿼리', 'QUERY' ],
	'url_wiki'                  => [ '0', '위키', 'WIKI' ],
];

$bookstoreList = [
	'Aladin.co.kr' => 'https://www.aladin.co.kr/search/wsearchresult.aspx?SearchTarget=All&SearchWord=$1',
	'National Library of Korea' => 'https://www.nl.go.kr/NL/contents/search.do?srchTarget=cheonggu&kwd=$1',
	'Naver' => 'https://book.naver.com/search/search.nhn?query=$1',
	'inherit' => true,
];

$datePreferences = [
	'default',
	'ISO 8601',
];
$defaultDateFormat = 'ko';
$dateFormats = [
	'ko time'            => 'H:i',
	'ko date'            => 'Y년 M월 j일 (D)',
	'ko both'            => 'Y년 M월 j일 (D) H:i',
];

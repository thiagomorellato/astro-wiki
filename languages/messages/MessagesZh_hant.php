<?php
/** Traditional Chinese (中文（繁體）)
 *
 * @file
 * @ingroup Languages
 *
 * @author Alexsh
 * @author Anakmalaysia
 * @author Andrew971218
 * @author Bencmq
 * @author BobChao
 * @author Breawycker
 * @author Byfserag
 * @author Ch.Andrew
 * @author Cwlin0416
 * @author Danny0838
 * @author FireJackey
 * @author Frankou
 * @author Gakmo
 * @author Gaoxuewei
 * @author Hakka
 * @author Horacewai2
 * @author Hydra
 * @author Hzy980512
 * @author Ianbu
 * @author Jidanni
 * @author Jimmy xu wrk
 * @author Justincheng12345
 * @author Kaganer
 * @author KaiesTse
 * @author Kayau
 * @author Kuailong
 * @author Lauhenry
 * @author Liangent
 * @author Liflon
 * @author Littletung
 * @author Liuxinyu970226
 * @author Mark85296341
 * @author Oapbtommy
 * @author Openerror
 * @author Pbdragonwang
 * @author PhiLiP
 * @author Philip
 * @author Radish10cm
 * @author Roc michael
 * @author Shinjiman
 * @author Shirayuki
 * @author Shizhao
 * @author Simon Shek
 * @author Skjackey tse
 * @author StephDC
 * @author Urhixidur
 * @author Waihorace
 * @author Winston Sung
 * @author Wmr89502270
 * @author Wong128hk
 * @author Wrightbus
 * @author Xiaomingyan
 * @author Yfdyh000
 * @author Yukiseaside
 * @author Yuyu
 * @author Zerng07
 * @author 乌拉跨氪
 * @author לערי ריינהארט
 */

$fallback = 'zh-tw, zh-hk, zh, zh-hans';

$fallback8bitEncoding = 'windows-950';

$namespaceNames = [
	NS_MEDIA            => '媒體',
	NS_SPECIAL          => '特殊',
	NS_TALK             => '討論',
	NS_USER             => '使用者',
	NS_USER_TALK        => '使用者討論',
	NS_PROJECT_TALK     => '$1討論',
	NS_FILE             => '檔案',
	NS_FILE_TALK        => '檔案討論',
	NS_MEDIAWIKI        => 'MediaWiki',
	NS_MEDIAWIKI_TALK   => 'MediaWiki討論',
	NS_TEMPLATE         => '模板',
	NS_TEMPLATE_TALK    => '模板討論',
	NS_HELP             => '說明',
	NS_HELP_TALK        => '說明討論',
	NS_CATEGORY         => '分類',
	NS_CATEGORY_TALK    => '分類討論',
];

$namespaceAliases = [
	'媒體' => NS_MEDIA,
	'媒體檔案' => NS_MEDIA,
	'媒體文件' => NS_MEDIA,
	'特殊' => NS_SPECIAL,
	'討論' => NS_TALK,
	'對話' => NS_TALK,
	'使用者' => NS_USER,
	'用戶' => NS_USER,
	'使用者討論' => NS_USER_TALK,
	'使用者對話' => NS_USER_TALK,
	'用戶討論' => NS_USER_TALK,
	'用戶對話' => NS_USER_TALK,
	'專案' => NS_PROJECT,
	# '項目' conflicted with WB_NS_ITEM
	'$1討論' => NS_PROJECT_TALK,
	'$1對話' => NS_PROJECT_TALK,
	'專案討論' => NS_PROJECT_TALK,
	# '項目討論' conflicted with WB_NS_ITEM_TALK
	'Image' => NS_FILE,
	'檔案' => NS_FILE,
	'文件' => NS_FILE,
	'圖像' => NS_FILE,
	'圖片' => NS_FILE,
	'Image_talk' => NS_FILE_TALK,
	'檔案討論' => NS_FILE_TALK,
	'檔案對話' => NS_FILE_TALK,
	'文件討論' => NS_FILE_TALK,
	'文件對話' => NS_FILE_TALK,
	'圖像討論' => NS_FILE_TALK,
	'圖像對話' => NS_FILE_TALK,
	'圖片討論' => NS_FILE_TALK,
	'模板' => NS_TEMPLATE,
	'樣板' => NS_TEMPLATE,
	'模板討論' => NS_TEMPLATE_TALK,
	'模板對話' => NS_TEMPLATE_TALK,
	'樣板討論' => NS_TEMPLATE_TALK,
	'樣板對話' => NS_TEMPLATE_TALK,
	'說明' => NS_HELP,
	'幫助' => NS_HELP,
	'使用說明' => NS_HELP,
	'說明討論' => NS_HELP_TALK,
	'幫助討論' => NS_HELP_TALK,
	'幫助對話' => NS_HELP_TALK,
	'使用說明討論' => NS_HELP_TALK,
	'分類' => NS_CATEGORY,
	'分類討論' => NS_CATEGORY_TALK,
	'分類對話' => NS_CATEGORY_TALK,
];

/** @phpcs-require-sorted-array */
$specialPageAliases = [
	'Activeusers'                => [ '活躍使用者' ],
	'Allmessages'                => [ '所有訊息' ],
	'AllMyUploads'               => [ '所有我的上傳', '所有我的檔案', '所有本人上載', '所有本人檔案' ],
	'Allpages'                   => [ '所有頁面' ],
	'Ancientpages'               => [ '最舊頁面', '最早頁面' ],
	'ApiHelp'                    => [ 'API說明', 'API使用說明' ],
	'ApiSandbox'                 => [ 'API沙盒' ],
	'AuthenticationPopupSuccess' => [ '認證成功彈窗' ],
	'AutoblockList'              => [ '自動封鎖清單', '列出自動封鎖' ],
	'Badtitle'                   => [ '無效標題' ],
	'Blankpage'                  => [ '空白頁面' ],
	'Block'                      => [ '封鎖', '封鎖IP', '封鎖使用者', '封禁', '封禁IP', '封禁使用者' ],
	'BlockList'                  => [ '封鎖清單', 'IP封鎖清單', '封禁列表', 'IP封禁列表' ],
	'Booksources'                => [ '書籍來源', '網路書源' ],
	'BotPasswords'               => [ '機器人密碼' ],
	'BrokenRedirects'            => [ '損壞的重新導向', '損壞的重定向頁' ],
	'Categories'                 => [ '分類', '頁面分類' ],
	'ChangeContentModel'         => [ '變更內容模型' ],
	'ChangeCredentials'          => [ '變更憑證' ],
	'ChangeEmail'                => [ '變更信箱', '修改郵箱' ],
	'ChangePassword'             => [ '變更密碼', '修改密碼', '密碼重設' ],
	'ComparePages'               => [ '頁面比較' ],
	'Confirmemail'               => [ '確認信箱', '確認電郵' ],
	'Contribute'                 => [ '做出貢獻' ],
	'Contributions'              => [ '使用者貢獻', '用戶貢獻' ],
	'CreateAccount'              => [ '建立帳號', '建立帳戶' ],
	'Deadendpages'               => [ '無連結頁面', '斷鏈頁面' ],
	'DeletedContributions'       => [ '已刪除的貢獻', '已刪除的用戶貢獻' ],
	'DeletePage'                 => [ '刪除頁面', '刪除' ],
	'Diff'                       => [ '編輯差異' ],
	'DoubleRedirects'            => [ '雙重的重新導向', '雙重重定向頁面' ],
	'EditPage'                   => [ '編輯頁面', '編輯' ],
	'EditRecovery'               => [ '編輯恢復' ],
	'EditTags'                   => [ '編輯標籤' ],
	'EditWatchlist'              => [ '編輯監視清單', '編輯監視列表' ],
	'Emailuser'                  => [ '寄信給使用者', '寄信', '電郵使用者' ],
	'ExpandTemplates'            => [ '展開模板' ],
	'Export'                     => [ '匯出', '匯出頁面' ],
	'Fewestrevisions'            => [ '最少修訂頁面' ],
	'FileDuplicateSearch'        => [ '重複檔案搜尋', '搜尋重複檔案' ],
	'Filepath'                   => [ '檔案路徑' ],
	'GoToInterwiki'              => [ '前往跨wiki頁面' ],
	'Import'                     => [ '匯入', '匯入頁面' ],
	'Interwiki'                  => [ '跨wiki', '跨維基' ],
	'Invalidateemail'            => [ '無效的信箱' ],
	'JavaScriptTest'             => [ 'JavaScript測試' ],
	'LinkAccounts'               => [ '連結帳號' ],
	'LinkSearch'                 => [ '連結搜尋', '搜尋網頁連結' ],
	'Listadmins'                 => [ '管理員清單', '管理員列表' ],
	'Listbots'                   => [ '機器人清單', '機械人列表' ],
	'ListDuplicatedFiles'        => [ '重複檔案清單', '重複檔案列表' ],
	'Listfiles'                  => [ '檔案清單', '圖片清單', '檔案列表', '圖像列表' ],
	'Listgrants'                 => [ '列出授權' ],
	'Listgrouprights'            => [ '群組權限清單', '使用者群組權限', '群組權限列表' ],
	'Listredirects'              => [ '重新導向清單', '重定向頁面列表' ],
	'Listusers'                  => [ '使用者清單', '使用者列表' ],
	'Lockdb'                     => [ '鎖定資料庫', '鎖定數據庫' ],
	'Log'                        => [ '日誌' ],
	'Lonelypages'                => [ '孤立頁面' ],
	'Longpages'                  => [ '過長的頁面', '長頁面' ],
	'MediaStatistics'            => [ '媒體統計' ],
	'MergeHistory'               => [ '合併歷史' ],
	'MIMEsearch'                 => [ 'MIME搜尋' ],
	'Mostcategories'             => [ '最多分類的頁面', '最多分類頁面' ],
	'Mostimages'                 => [ '被連結最多的檔案', '最多連結檔案' ],
	'Mostinterwikis'             => [ '最多跨wiki連結的頁面', '最多_Interwiki_連結的頁面', '最多跨wiki連結' ],
	'Mostlinked'                 => [ '被連結最多的頁面', '最多連結頁面' ],
	'Mostlinkedcategories'       => [ '被連結最多的分類', '最多連結分類' ],
	'Mostlinkedtemplates'        => [ '被引用最多的頁面', '被連結最多的模板', '被使用最多的模板' ],
	'Mostrevisions'              => [ '最多修訂的頁面', '最多修訂頁面' ],
	'Movepage'                   => [ '移動頁面' ],
	'Mute'                       => [ '靜音' ],
	'Mycontributions'            => [ '我的貢獻' ],
	'MyLanguage'                 => [ '我的語言' ],
	'Mylog'                      => [ '我的日誌' ],
	'Mypage'                     => [ '我的使用者頁面', '我的用戶頁' ],
	'Mytalk'                     => [ '我的對話', '我的討論頁' ],
	'Myuploads'                  => [ '我的上傳', '我的上載', '我的檔案' ],
	'NamespaceInfo'              => [ '命名空間資訊' ],
	'Newimages'                  => [ '新增檔案', '新增圖片' ],
	'Newpages'                   => [ '新增頁面', '新頁面' ],
	'NewSection'                 => [ '新章節' ],
	'PageData'                   => [ '頁面資料' ],
	'PageHistory'                => [ '頁面歷史', '歷史' ],
	'PageInfo'                   => [ '頁面資訊', '資訊' ],
	'PageLanguage'               => [ '頁面語言' ],
	'PagesWithProp'              => [ '擁有屬性的頁面', '帶屬性頁面' ],
	'PasswordPolicies'           => [ '密碼原則' ],
	'PasswordReset'              => [ '重設密碼' ],
	'PermanentLink'              => [ '固定連結', '靜態連結', '永久連結' ],
	'Preferences'                => [ '偏好設定' ],
	'Prefixindex'                => [ '前綴索引', '字首索引' ],
	'Protectedpages'             => [ '受保護頁面', '已保護頁面' ],
	'Protectedtitles'            => [ '受保護標題', '已保護標題' ],
	'ProtectPage'                => [ '保護頁面', '保護' ],
	'Purge'                      => [ '更新快取' ],
	'RandomInCategory'           => [ '隨機分類頁面', '於分類中隨機' ],
	'Randompage'                 => [ '隨機頁面' ],
	'Randomredirect'             => [ '隨機重新導向', '隨機重定向頁面' ],
	'Randomrootpage'             => [ '隨機根頁面' ],
	'Recentchanges'              => [ '最近變更', '最近更改' ],
	'Recentchangeslinked'        => [ '已連結的最近變更', '相關變更', '連出更改' ],
	'Redirect'                   => [ '重新導向', '重定向' ],
	'RemoveCredentials'          => [ '移除憑證' ],
	'Renameuser'                 => [ '重新命名使用者' ],
	'ResetTokens'                => [ '重設密鑰', '覆寫令牌' ],
	'Revisiondelete'             => [ '修訂刪除', '刪除或恢復版本' ],
	'RunJobs'                    => [ '執行作業', '運行工作' ],
	'Search'                     => [ '搜尋' ],
	'Shortpages'                 => [ '過短的頁面', '短頁面' ],
	'Specialpages'               => [ '特殊頁面' ],
	'Statistics'                 => [ '統計資訊' ],
	'Tags'                       => [ '標籤' ],
	'TalkPage'                   => [ '討論頁' ],
	'TrackingCategories'         => [ '追蹤分類', '跟蹤分類' ],
	'Unblock'                    => [ '解除封鎖', '解除封禁', '解禁' ],
	'Uncategorizedcategories'    => [ '未分類的分類', '未歸類分類' ],
	'Uncategorizedimages'        => [ '未分類的檔案', '未分類的圖片', '未歸類檔案' ],
	'Uncategorizedpages'         => [ '未分類的頁面', '未歸類頁面' ],
	'Uncategorizedtemplates'     => [ '未分類的模板', '未歸類模板' ],
	'Undelete'                   => [ '取消刪除' ],
	'UnlinkAccounts'             => [ '解除連結帳號' ],
	'Unlockdb'                   => [ '解除鎖定資料庫', '解除資料庫鎖定' ],
	'Unusedcategories'           => [ '未使用的分類', '未使用分類' ],
	'Unusedimages'               => [ '未使用的檔案', '未使用檔案' ],
	'Unusedtemplates'            => [ '未使用的模板', '未使用模板' ],
	'Unwatchedpages'             => [ '未監視的頁面', '未被監視的頁面' ],
	'Upload'                     => [ '上傳', '上載檔案' ],
	'UploadStash'                => [ '上傳儲藏庫' ],
	'Userlogin'                  => [ '使用者登入' ],
	'Userlogout'                 => [ '使用者登出' ],
	'Userrights'                 => [ '使用者權限' ],
	'Version'                    => [ '版本', '版本資訊' ],
	'Wantedcategories'           => [ '需要的分類', '待撰分類' ],
	'Wantedfiles'                => [ '需要的檔案' ],
	'Wantedpages'                => [ '需要的頁面', '待撰頁面' ],
	'Wantedtemplates'            => [ '需要的模板' ],
	'Watchlist'                  => [ '監視清單' ],
	'Whatlinkshere'              => [ '連入頁面' ],
	'Withoutinterwiki'           => [ '無跨wiki連結頁面', '無跨維基連結頁面' ],
];

/** @phpcs-require-sorted-array */
$magicWords = [
	'contentlanguage'           => [ '1', '內容語言', '内容语言', 'CONTENTLANGUAGE', 'CONTENTLANG' ],
	'currentday'                => [ '1', '今天', 'CURRENTDAY' ],
	'currentmonth'              => [ '1', '本月', '本月2', 'CURRENTMONTH', 'CURRENTMONTH2' ],
	'currentmonthabbrev'        => [ '1', '本月縮寫', '本月简称', 'CURRENTMONTHABBREV' ],
	'currenttime'               => [ '1', '目前時間', '当前时间', '此时', 'CURRENTTIME' ],
	'currentversion'            => [ '1', '目前版本', '当前版本', 'CURRENTVERSION' ],
	'displaytitle'              => [ '1', '顯示標題', '显示标题', 'DISPLAYTITLE' ],
	'forcetoc'                  => [ '0', '__強制目錄__', '__强显目录__', '__FORCETOC__' ],
	'gender'                    => [ '0', '性別:', '性:', '性别:', 'GENDER:' ],
	'hiddencat'                 => [ '1', '__隱藏分類__', '__隐藏分类__', '__HIDDENCAT__' ],
	'img_alt'                   => [ '1', '替代文字', '替代=$1', '替代文本=$1', 'alt=$1' ],
	'img_border'                => [ '1', '邊框', '边框', 'border' ],
	'img_bottom'                => [ '1', '垂直置底', 'bottom' ],
	'img_center'                => [ '1', '置中', '居中', 'center', 'centre' ],
	'img_class'                 => [ '1', '類別=$1', '类=$1', 'class=$1' ],
	'img_framed'                => [ '1', '有框', 'framed', 'enframed', 'frame' ],
	'img_frameless'             => [ '1', '無框', '无框', 'frameless' ],
	'img_lang'                  => [ '1', '語言=$1', 'lang=$1' ],
	'img_left'                  => [ '1', '左', 'left' ],
	'img_link'                  => [ '1', '連結=$1', '链接=$1', 'link=$1' ],
	'img_manualthumb'           => [ '1', '縮圖=$1', '缩略图=$1', 'thumbnail=$1', 'thumb=$1' ],
	'img_middle'                => [ '1', '垂直置中', 'middle' ],
	'img_none'                  => [ '1', '無', '无', 'none' ],
	'img_page'                  => [ '1', '頁=$1', '$1頁', '页数=$1', '$1页', 'page=$1', 'page $1' ],
	'img_right'                 => [ '1', '右', 'right' ],
	'img_sub'                   => [ '1', '下標', 'sub' ],
	'img_super'                 => [ '1', '上標', 'super', 'sup' ],
	'img_text_bottom'           => [ '1', '文字置底', 'text-bottom' ],
	'img_text_top'              => [ '1', '文字置頂', 'text-top' ],
	'img_thumbnail'             => [ '1', '縮圖', '缩略图', 'thumbnail', 'thumb' ],
	'img_top'                   => [ '1', '垂直置頂', 'top' ],
	'img_width'                 => [ '1', '$1像素', '$1px' ],
	'language'                  => [ '0', '#語言', '#语言', '#LANGUAGE' ],
	'localurl'                  => [ '0', '本地URL:', 'LOCALURL:' ],
	'localurle'                 => [ '0', '本地URLE:', 'LOCALURLE:' ],
	'msg'                       => [ '0', '訊息:', 'MSG:' ],
	'namespace'                 => [ '1', '命名空間', '名字空间', 'NAMESPACE' ],
	'namespacenumber'           => [ '1', '命名空間數', '名字空间编号', 'NAMESPACENUMBER' ],
	'nocontentconvert'          => [ '0', '__不轉換內容__', '__不转换内容__', '__NOCONTENTCONVERT__', '__NOCC__' ],
	'noeditsection'             => [ '0', '__無段落編輯__', '__无编辑段落__', '__无段落编辑__', '__NOEDITSECTION__' ],
	'nogallery'                 => [ '0', '__無圖庫__', '__无图库__', '__NOGALLERY__' ],
	'notitleconvert'            => [ '0', '__不轉換標題__', '__不转换标题__', '__NOTITLECONVERT__', '__NOTC__' ],
	'notoc'                     => [ '0', '__無目錄__', '__无目录__', '__NOTOC__' ],
	'ns'                        => [ '0', '命名空間:', '名字空间:', 'NS:' ],
	'nse'                       => [ '0', '命名空間E:', '名字空间E:', 'NSE:' ],
	'numberofactiveusers'       => [ '1', '活躍使用者人數', '活跃用户数', 'NUMBEROFACTIVEUSERS' ],
	'numberofadmins'            => [ '1', '管理員數', '管理员数', 'NUMBEROFADMINS' ],
	'numberofarticles'          => [ '1', '文章數', '条目数', 'NUMBEROFARTICLES' ],
	'numberoffiles'             => [ '1', '檔案數', '文件数', 'NUMBEROFFILES' ],
	'numberofpages'             => [ '1', '頁面數', '页面数', 'NUMBEROFPAGES' ],
	'numberofusers'             => [ '1', '使用者人數量', '用户数', 'NUMBEROFUSERS' ],
	'pageid'                    => [ '0', '頁面ID', '页面ID', 'PAGEID' ],
	'pagename'                  => [ '1', '頁面名稱', '页名', '页面名', '页面名称', 'PAGENAME' ],
	'pagesincategory_files'     => [ '0', '檔案', 'files' ],
	'pagesincategory_pages'     => [ '0', '頁面', 'pages' ],
	'redirect'                  => [ '0', '#重新導向', '#重定向', '#REDIRECT' ],
	'revisionuser'              => [ '1', '修訂使用者', 'REVISIONUSER' ],
	'rootpagename'              => [ '1', '根頁面名稱', 'ROOTPAGENAME' ],
	'rootpagenamee'             => [ '1', '根頁面名稱E', 'ROOTPAGENAMEE' ],
	'safesubst'                 => [ '0', '安全替換:', '安全替代:', 'SAFESUBST:' ],
	'server'                    => [ '0', '伺服器', '服务器', 'SERVER' ],
	'servername'                => [ '0', '伺服器名稱', '服务器名', 'SERVERNAME' ],
	'sitename'                  => [ '1', '網站名稱', '站点名称', 'SITENAME' ],
	'staticredirect'            => [ '1', '__靜態重新導向__', '__静态重定向__', '__STATICREDIRECT__' ],
	'subst'                     => [ '0', '替換:', '替代:', 'SUBST:' ],
	'talkspace'                 => [ '1', '對話空間', '讨论空间', '讨论名字空间', 'TALKSPACE' ],
	'toc'                       => [ '0', '__目錄__', '__目录__', '__TOC__' ],
	'url_query'                 => [ '0', '查詢', 'QUERY' ],
];

$datePreferences = [
	'default',
	'ISO 8601',
];

$defaultDateFormat = 'zh';

$dateFormats = [
	'zh time' => 'H:i',
	'zh date' => 'Y年n月j日 (l)',
	'zh both' => 'Y年n月j日 (D) H:i',
];

$bookstoreList = [
	'博客來書店' => 'http://www.books.com.tw/exep/prod/booksfile.php?item=$1',
	'三民書店' => 'http://www.sanmin.com.tw/page-qsearch.asp?ct=search_isbn&qu=$1',
	'天下書店' => 'http://www.cwbook.com.tw/search/result1.jsp?field=2&keyWord=$1',
	'新絲路書店' => 'http://www.silkbook.com/function/Search_list_book_data.asp?item=5&text=$1'
];

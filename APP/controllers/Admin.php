<?php
use Illuminate\Database\Capsule\Manager as DB;

class AdminController extends CoreController
{
	public function indexAction() {
		$adminModel	=	new adminModel();
		$username	=	$this->auth->name.' '.$adminModel->getRolename($this->auth->role);
		
		$this->_view->assign('appTitle', DB::table('info')->find(1)['value']);
		$this->_view->assign('username', $username);
	}
	public function mainAction() {
        /**
		*服务器信息
		**/
		//$dbo 	= 	new Model();
		$rs['mysqlVersion']	=	DB::select("select version() as version")[0]['version'];				
		
		$rs['artclenum']	=	DB::table('news')->where('status','=',1)->count();
		$rs['uartclenum']	=	DB::table('news')->where('status','=',0)->count();
		$rs['commentnum']	=	DB::table('newsreply')->where('status','=',1)->count();
		$rs['ucommentnum']	=	DB::table('newsreply')->where('status','=',0)->count();
		$rs['administrator']=	DB::table('admin')->where('roles_id','=',1)->pluck('username');	
		
		isset($_COOKIE) ? $rs['ifcookie']="SUCCESS" : $rs['ifcookie']="FAIL";
		$rs['sysversion']	=	PHP_VERSION;	//PHP版本
		$rs['max_upload']	=	ini_get('upload_max_filesize') ? ini_get('upload_max_filesize') : 'Disabled';	//最大上传限制
		$rs['max_ex_time']	=	ini_get('max_execution_time').' 秒';	//最大执行时间
		$rs['sys_mail']		=	ini_get('sendmail_path') ? 'Unix Sendmail ( Path: '.ini_get('sendmail_path').')' :( ini_get('SMTP') ? 'SMTP ( Server: '.ini_get('SMTP').')': 'Disabled' );$rs['uploadpath']	  =	  $this->config['application']['uploadpath'];
		$rs['dbpath']	    =	$this->config['application']['dbpath'];
		
		//邮件支持模式
		$rs['systemtime']	=	date("Y-m-d H:i:s");	//服务器所在时间
		$rs['onlineip']		=	getIp();				//当前IP
		if( function_exists("imagealphablending") && function_exists("imagecreatefromjpeg") && function_exists("ImageJpeg") ){
			$rs['gdpic']	=	"支持";
		}else{
			$rs['gdpic']	=	"不支持";
		}
		$rs['allow_url_fopen']	=	ini_get('allow_url_fopen')?"On 支持采集数据":"OFF 不支持采集数据";
		$rs['safe_mode']		=	ini_get('safe_mode')?"打开":"关闭";
		$rs['DOCUMENT_ROOT']	=	$_SERVER["DOCUMENT_ROOT"];	//程序所在磁盘物理位置
		$rs['SERVER_ADDR']		=	$_SERVER["SERVER_ADDR"]?$_SERVER["SERVER_ADDR"]:$_SERVER["LOCAL_ADDR"];		//服务器IP
		$rs['SERVER_PORT']		=	$_SERVER["SERVER_PORT"];		//服务器端口
		$rs['SERVER_SOFTWARE']	=	$_SERVER["SERVER_SOFTWARE"];	//服务器软件
		$rs['SCRIPT_FILENAME']	=	$_SERVER["SCRIPT_FILENAME"]?$_SERVER["SCRIPT_FILENAME"]:$_SERVER["PATH_TRANSLATED"];//当前文件路径
		$rs['SERVER_NAME']		=	$_SERVER["SERVER_NAME"];	//域名
	
		//获取ZEND的版本		
		ob_start();
		phpinfo();
		$phpinfo	=	ob_get_contents();
		ob_end_clean();
		preg_match("/(v\d\.\d\.\d)/s",	$phpinfo,	$zenddb);
		
		$rs['zendVersion']		=	$zenddb[1]?$zenddb[1]:"未知/可能没安装";
		$rs['memory_user_limit']=	ini_get('memory_limit');    //最大执行时间/空间限制内存
		$rs['file_uploads']		=	ini_get('file_uploads')?"允许":"不允许"; //是否允许上传文件
		$rs['upload_tmp_dir']	=	isset($_SERVER['TMP'])?$_SERVER['TMP']:ini_get("upload_tmp_dir");
		
        $this->_view->assign('systemMsg',         $rs);
    }
	
	public function infoAction() {
		$this->_view->assign('uniqid',	 uniqid());
    }
	public function infoGetAction() {
		$page   =	$this->getPost('page', 1);
		$limit  =	$this->getPost('rows', 10);
		$offset	=	($page-1)*$limit;				
		$sort	=	$this->getPost('sort',  'id');
		$order	=	$this->getPost('order', 'asc');
		$keywords	= $this->getPost('keywords', '');
		$query		= DB::table('info');
		if($keywords!==''){
			$query	=	$query	->where('key','like',"%{$keywords}%")
								->orWhere('caption','like',"%{$keywords}%")
								->orWhere('value','like',"%{$keywords}%");
		}		
		$total		= $query->count();
		$rows 		= $query->orderBy($sort,$order)
						    ->offset($offset)
						    ->limit($limit)
						    ->get();
		//记录SQL日志
		//DB::enableQueryLog();
		//Log::out('sql', json_encode(DB::getQueryLog(), JSON_UNESCAPED_UNICODE));				
		json(['total'=>$total, 'rows'=>$rows]);
    }
	public function infoCellUpdateAction(){
		do{
			if( $this->method=='POST' ){
				$rows	=	$this->getPost()['data'];
				if(empty($rows)||empty($rows['id'])){
					$result	= array(
								'code'=>	'300',
								'msg'	=>	'请传入数据，并确认id值',
							);
					break;
				}
				$id		=	$rows['id'];				
				$info	=	new infoModel;
				$key	=	$info->primaryKey;
				unset($rows['id']);
				if( $info->where($key,'=',$id)->update($rows)!==FALSE  ){
					$result	= array(
								'code'=>	'200',
								'msg'	=>	'更新成功',										
							);
				}else{
					$result	= array(
								'code'=>	'300',
								'msg'	=>	'更新失败',										
							);
				}
			}else{
				$result	= array(
								'code'=>	'300',
								'msg'	=>	'提交方式有误',										
							);
			}
		}while(FALSE);
		json($result);
    }
	public function infoaddAction(){
		return TRUE;
	}
	public function infoincreaseAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$caption	= $this->getPost('caption', '');
			$key		= $this->getPost('key', 	'');
			$value		= $this->getPost('value', 	'');
			$encode		= $this->getPost('encode', 	0);
			if( empty($caption) ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'名称不能为空',
						);
				break;
			}
			if( empty($key) ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'键不能为空',
						);
				break;
			}
			if( $value==='' ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'值不能为空',
						);
				break;
			}
			$rows	= array(
								'key'		=>	$key,
								'caption'	=>	$caption,
								'value'		=>	$value,
								'encode'	=>	$encode,
								'created_at'=>	date('Y-m-d H:i:s'),
					);
			if( (new infoModel)->insert($rows) ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'添加数据成功',	
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据插入失败',	
						);
			}
		}while(FALSE);
		
		json($result);
    }
	public	function infoEditAction(){
		$id	  = intval($this->getQuery('id', 0));
		if($id==0){	return false;	}		
		
     	$rows  	= DB::table('info')->find($id);		
		if( $rows['encode']==1 ){
			$rows['value']	=	base64_decode($rows['value']);
		}
		$this->_view->assign('dataset', $rows);
    }
	public function infoupdateAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$id			= intval($this->getPost('id',  0));
			$caption	= $this->getPost('caption', '');
			$key		= $this->getPost('key', 	'');
			$value		= $this->getPost('value', 	'');
			$encode		= $this->getPost('encode', 	0);
			if( empty($id) ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'主键不能为空',
						);
				break;
			}
			if( empty($caption) ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'名称不能为空',
						);
				break;
			}
			if( empty($key) ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'键不能为空',
						);
				break;
			}
			if( $value==='' ){
				$result	= array(
							'code'	=>	'300',
							'msg'	=>	'值不能为空',
						);
				break;
			}
			$rows	= array(
								'key'		=>	$key,
								'caption'	=>	$caption,
								'value'		=>	$value,
								'encode'	=>	$encode,
					);
			$info	=	new infoModel;
			$key	=	$info->primaryKey;
			unset($rows['id']);		
			if( $info->where($key,'=',$id)->update($rows) ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'更新数据成功',	
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据更新失败',	
						);
			}
		}while(FALSE);
		
		json($result);
    }
	public function infodeleteAction(){	
		do{
			if($this->method!='POST'){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;				
			}
			$id	= $this->getPost('id', 0);
			if( empty($id) ){
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'参数为空',
						);
				break;
			}
			if( DB::table('info')->where('id','=',$id)->delete() ){
				$result		= array(
							'code'		=>	'200',
							'msg'		=>	'删除成功',
							);						
			}else{
				$result		= array(
							'code'		=>	'300',
							'msg'		=>	'删除失败',
							);
			}
		}while(FALSE);	
		
		json($result);
    }
	
	
	public function menusAction(){		    	
		$this->_view->assign('uniqid',	 uniqid());
    }	
	public function menusGetAction() {			
		$sort	=	$this->getPost('sort', 'sortorder');
		$order	=	$this->getPost('order', 'desc');
		$keywords	= $this->getPost('keywords', '');
		$query		= DB::table('menus');
		if($keywords!==''){
			$query	=	$query	->where('title','like',"%{$keywords}%")
								->orWhere('links','like',"%{$keywords}%");
		}else{
			$query	=	$query	->where('up','=','0');
		}
		$total		= $query->count();
		$rows 		= $query->orderBy($sort,$order)							
							->get();
		foreach($rows	as	$k=>$v){
				$rows[$k]['children']	=	DB::table('menus')->where('up','=',$v['id'])
															  ->orderBy($sort,$order)
															  ->get();
		}					
		json(['total'=>$total, 'rows'=>$rows]);		
    }
	public function menusaddAction(){
		$dataset  	= DB::table('menus')->where('up','=',0)->get();
		$this->_view->assign('dataset', $dataset);
    }	
	public function menusincreaseAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');
			$up			= $this->getPost('up', 	0);
			$sortorder	= $this->getPost('sortorder', 0);			
			if( empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'菜单名称不能为空',
						);
				break;
			}
			$rows	= array(
								'title'		=>	$title,
								'links'		=>	$links,
								'up'		=>	$up,
								'sortorder'	=>	$sortorder,
								'created_at'=>	date('Y-m-d H:i:s'),
					);
			if( DB::table('menus')->insert($rows) ){
				$result	= array(
							'code'	=>	'200',
							'msg'		=>	'操作成功',								
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据插入失败',	
						);
			}
		}while(FALSE);
		
		die(json_encode($result));
    }	
	public	function menuseditAction(){    
		$dataset  	= DB::table('menus')->where('up','=',0)->get();
		
		$id			= intval($this->get('id', NULL));
		if($id==NULL){	return false;	}		
     	$mymenu  	= DB::table('menus')->find($id);

		$this->_view->assign('dataset', $dataset);
		$this->_view->assign('mymenu',  $mymenu);
    }	
    public function menusupdateAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'操作失败',										
						);
				break;
			}
			$id			= $this->getPost('id', '');
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');
			$up			= $this->getPost('up', 	0);
			$sortorder	= $this->getPost('sortorder', 0);			
			if( empty($id)||empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'菜单id与标题不能为空',
						);
				break;
			}
			if( $id==$up ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'上级菜单循环设置.',
						);
				break;
			}
			$rows	=	array(	
							'title'		=>	$title,
							'links'		=>	$links,
							'up'		=>	$up,
							'sortorder'	=>	$sortorder,
							'updated_at'=>	date('Y-m-d H:i:s'),
						);
			if( DB::table('menus')->where('id','=',$id)->update($rows)!==FALSE ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'更新失败',	
						);
			}
		}while(FALSE);
    			
		die(json_encode($result));
    }		
    public function menusdeleteAction(){	
		do{
			if($this->method!='POST'){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;				
			}
			$id	= $this->get('id', '');
			if( empty($id) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'参数为空',
						);
				break;
			}
			if(DB::table('menus')->delete($id)){
				$result		= array(
							'code'	=>	'200',
							'msg'		=>	'操作成功',
							'navTabId'		=>	'menus',
							);						
			}else{
				$result		= array(
							'code'	=>	'300',
							'msg'		=>	'删除失败',
							);
			}
		}while(FALSE);	
		
		die(json_encode($result));    	
    }
	
	
	public function imagesAction(){
		$this->_view->assign('uniqid',	 uniqid());
    }
	public function imagesGetAction() {
		$page   =	$this->getPost('page', 1);
		$limit  =	$this->getPost('rows', 10);
		$offset	=	($page-1)*$limit;			
		$sort	=	$this->getPost('sort', 'sortorder');
		$order	=	$this->getPost('order', 'desc');
		$keywords	= trim($this->getPost('keywords', ''));		
		$query		= DB::table('images');
		if($keywords!==''){
			$query	=	$query	->where('title','like',"%{$keywords}%")
								->orWhere('file','like',"%{$keywords}%")
								->orWhere('links','like',"%{$keywords}%");
		}		
		$total		= $query->count();
		$rows 		= $query->orderBy($sort,$order)
							->offset($offset)
							->limit($limit)
							->select('id','file','title',DB::raw('if(type=0,"APP欢迎页","主页动图") as type'),'links',DB::raw('if(status=1,"激活","失效") as status'),'sortorder','created_at','updated_at')
							->get();
		json(['total'=>$total, 'rows'=>$rows]);
    }
	public function imagesaddAction(){
		return true;
    }	
	public function imagesincreaseAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$file		= $this->getPost('images',[])[0];
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');
			$type		= $this->getPost('type', 0);			
			$sortorder	= $this->getPost('sortorder', 0);			
			$status		= $this->getPost('status', 	0);
			if( empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'图片标题不能为空',
						);
				break;
			}
			$rows	= array(
								'file'		=>	$file,
								'title'		=>	$title,
								'links'		=>	$links,
								'type'		=>	$type,
								'sortorder'	=>	$sortorder,
								'status'	=>	$status,
								'created_at'=>	date('Y-m-d H:i:s'),
			);
			if( DB::table('images')->insert($rows) ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据插入失败',	
						);
			}
		}while(FALSE);
		
		die(json_encode($result));
    }	
	public	function imageseditAction(){
		$id			= $this->get('id', NULL);
		if($id==NULL){	return false;	}		
     	$dataset  	= DB::table('images')->find(intval($id));

		$this->_view->assign('dataset', $dataset);
    }
    public function imagesupdateAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'操作失败',										
						);
				break;
			}
			$id			= $this->getPost('id', '');
			$file		= $this->getPost('images',[])[0];
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');		
			$type		= $this->getPost('type', 0);				
			$sortorder	= $this->getPost('sortorder', 0);			
			$status		= $this->getPost('status', 	0);
			if( empty($id)||empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'图片id与标题不能为空',
						);
				break;
			}
			$rows	=	array(	
							'file'		=>	$file,
							'title'		=>	$title,
							'links'		=>	$links,
							'type'		=>	$type,
							'sortorder'	=>	$sortorder,
							'status'	=>	$status,
							'updated_at'=>	date('Y-m-d H:i:s'),
						);						
			if( DB::table('images')->where('id','=',$id)->update($rows)!==FALSE ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'更新失败',	
						);
			}
		}while(FALSE);
    			
		die(json_encode($result));
    }		
    public function imagesdeleteAction(){	
		do{
			if($this->method!='POST'){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;				
			}
			$id	= $this->get('id', '');
			if( empty($id) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'参数为空',
						);
				break;
			}
			if(DB::table('images')->delete($id)){
				$result		= array(
							'code'	=>	'200',
							'msg'		=>	'操作成功',
							);						
			}else{
				$result		= array(
							'code'	=>	'300',
							'msg'		=>	'删除失败',
							);
			}
		}while(FALSE);	
		
		die(json_encode($result));    	
    }
	
	public function friendlinkAction(){
    	$this->_view->assign('uniqid',	 uniqid());
    }
	public function friendlinkGetAction() {
		$page   =	$this->getPost('page', 1);
		$limit  =	$this->getPost('rows', 10);
		$offset	=	($page-1)*$limit;			
		$sort	=	$this->getPost('sort',  'sortorder');
		$order	=	$this->getPost('order', 'desc');
		$keywords	= trim($this->getPost('keywords', ''));		
		$query		= DB::table('friendlink');
		if($keywords!==''){
			$query	=	$query	->where('title','like',"%{$keywords}%")
								->orWhere('file','like',"%{$keywords}%")
								->orWhere('links','like',"%{$keywords}%");
		}		
		$total		= $query->count();
		$rows 		= $query->orderBy($sort,$order)
							->offset($offset)
							->limit($limit)
							->select('id','file','title','links',DB::raw('if(status=1,"激活","失效") as status'),'sortorder','created_at','updated_at')
							->get();
		json(['total'=>$total, 'rows'=>$rows]);
    }
	public function friendlinkaddAction(){
		return true;
    }
	public function friendlinkincreaseAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$file		= $this->getPost('images',[])[0];
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');
			$sortorder	= $this->getPost('sortorder', 0);			
			$status		= $this->getPost('status', 	0);
			if( empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'图片标题不能为空',
						);
				break;
			}
			$rows	= array(
								'file'		=>	$file,
								'title'		=>	$title,
								'links'		=>	$links,
								'sortorder'	=>	$sortorder,
								'status'	=>	$status,
								'created_at'=>	date('Y-m-d H:i:s'),
			);
			if( DB::table('friendlink')->insert($rows) ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据插入失败',	
						);
			}
		}while(FALSE);
		
		die(json_encode($result));
    }	
	public	function friendlinkeditAction(){
		$id			= $this->get('id', NULL);
		if($id==NULL){	return false;	}		
     	$dataset  	= DB::table('friendlink')->find(intval($id));

		$this->_view->assign('dataset', $dataset);
    }
    public function friendlinkupdateAction(){
		do{
			$id			= $this->getPost('id', '');
			$file		= $this->getPost('images',[])[0];
			$title		= $this->getPost('title', '');
			$links		= $this->getPost('links', '');			
			$sortorder	= $this->getPost('sortorder', 0);			
			$status		= $this->getPost('status', 	0);
			if( empty($id)||empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'ID与标题不能为空',
						);
				break;
			}
			$rows	=	array(	
							'file'		=>	$file,
							'title'		=>	$title,
							'links'		=>	$links,
							'sortorder'	=>	$sortorder,
							'status'	=>	$status,
							'updated_at'=>	date('Y-m-d H:i:s'),
						);						
			if( DB::table('friendlink')->where('id','=',$id)->update($rows)!==FALSE ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'更新失败',	
						);
			}
		}while(FALSE);
    			
		die(json_encode($result));
    }		
    public function friendlinkdeleteAction(){	
		do{
			if($this->method!='POST'){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;				
			}
			$id	= $this->get('id', '');
			if( empty($id) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'参数为空',
						);
				break;
			}
			if(DB::table('friendlink')->delete($id)){
				$result		= array(
							'code'	=>	'200',
							'msg'		=>	'操作成功',
							);						
			}else{
				$result		= array(
							'code'	=>	'300',
							'msg'		=>	'删除失败',
							);
			}
		}while(FALSE);	
		
		die(json_encode($result));    	
    }
	
	
	public function changepwdAction() {	
		$this->_view->assign('username', $this->auth->name);
    }	
	public function changepwddoAction(){
		do{
			$id			=	$this->auth->user_id;
			$oldPassword=	$this->getPost('oldpassword', '');
			$newPassword=	$this->getPost('newpassword', '');
			$rePassword=	$this->getPost('repassword', '');
			do{
				if( empty($id)||empty($oldPassword)||empty($rePassword) ){
					$result	= array(
								'code'	=>	'300',
								'msg'		=>	'原密码或新密码不能为空',
							);	
					break;				
				}
				if( $rePassword!=$newPassword ){
					$result	= array(
								'code'	=>	'300',
								'msg'		=>	'重复密码不一致.',
							);	
					break;				
				}
				/***检查旧密码是否正确***/
				$rows	=	DB::table('admin')->find($id);				
				if( $rows['password']!==md5($oldPassword) ){
					$result	= array(
								'code'	=>	'300',
								'msg'	=>	'原密码输入有误.',
							);	
					break;
				}
				unset($rows);
				$rows	=	['password'	=>	md5($newPassword)];
				if( DB::table('admin')->where('id','=',$id)->update($rows)!==FALSE ){
						$result	= array(
								'code'	=>	'200',
								'msg'	=>	'操作成功',
						);
				}else{
						$result	= array(
								'code'	=>	'300',
								'msg'	=>	'更新失败,请多试几下',	
						);
				}			
			}while(FALSE);
		}while(FALSE);
		
		die(json_encode($result));
	}	
	
	
	public function cityAction(){		    	
		$this->_view->assign('uniqid',	 uniqid());
    }	
	public function cityGetAction() {			
		$sort	=	$this->getPost('sort', 'sortorder');
		$order	=	$this->getPost('order', 'desc');
		$keywords	= $this->getPost('keywords', '');
		$query		= DB::table('city');
		if($keywords!==''){
			$query	=	$query	->where('name','like',"%{$keywords}%");
		}else{
			$query	=	$query	->where('up','=','0');
		}
		$total		= $query->count();
		$rows 		= $query->orderBy($sort,$order)							
							->get();
		foreach($rows	as	$k=>$v){
				$rows[$k]['children']	=	DB::table('city')->where('up','=',$v['id'])
															  ->orderBy($sort,$order)
															  ->get();
		}					
		json(['total'=>$total, 'rows'=>$rows]);		
    }
	public function cityaddAction(){
		$dataset  	= DB::table('city')->where('up','=',0)->get();
		$this->_view->assign('dataset', $dataset);
    }	
	public function cityincreaseAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'操作失败',										
						);
				break;
			}
			$title		= $this->getPost('title', '');
			$up			= $this->getPost('up', 	0);
			$sortorder	= $this->getPost('sortorder', 0);			
			if( empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'配件名称不能为空',
						);
				break;
			}
			$rows	= array(
								'name'		=>	$title,
								'links'		=>	$links,
								'up'		=>	$up,
								'sortorder'	=>	$sortorder,
								'created_at'=>	date('Y-m-d H:i:s'),
					);
			if( DB::table('city')->insert($rows) ){
				$result	= array(
							'code'	=>	'200',
							'msg'		=>	'操作成功',								
						);
			}else{
				$result	= array(
							'code'=>	'300',
							'msg'	=>	'数据插入失败',	
						);
			}
		}while(FALSE);
		
		die(json_encode($result));
    }	
	public	function cityeditAction(){    
		$dataset  	= DB::table('city')->where('up','=',0)->get();
		
		$id			= intval($this->get('id', NULL));
		if($id==NULL){	return false;	}		
     	$mymenu  	= DB::table('city')->find($id);

		$this->_view->assign('dataset', $dataset);
		$this->_view->assign('mymenu',  $mymenu);
    }	
    public function cityupdateAction(){
		do{
			if( $this->method!='POST' ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'操作失败',										
						);
				break;
			}
			$id			= $this->getPost('id', '');
			$title		= $this->getPost('title', '');
			$up			= $this->getPost('up', 	0);
			$sortorder	= $this->getPost('sortorder', 0);			
			if( empty($id)||empty($title) ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'id与标题不能为空',
						);
				break;
			}
			if( $id==$up ){
				$result	= array(
							'code'	=>	'300',
							'msg'		=>	'上级项目循环设置.',
						);
				break;
			}
			$rows	=	array(	
							'name'		=>	$title,
							'up'		=>	$up,
							'sortorder'	=>	$sortorder,
							'updated_at'=>	date('Y-m-d H:i:s'),
						);
			if( DB::table('city')->where('id','=',$id)->update($rows)!==FALSE ){
				$result	= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',	
						);
			}else{
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'更新失败',	
						);
			}
		}while(FALSE);
    			
		die(json_encode($result));
    }		
    public function citydeleteAction(){	
		do{
			if($this->method!='POST'){
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'操作失败',										
						);
				break;				
			}
			$id	= $this->get('id', '');
			if( empty($id) ){
				$result	= array(
							'code'		=>	'300',
							'msg'		=>	'参数为空',
						);
				break;
			}
			if(DB::table('city')->delete($id)){
				$result		= array(
							'code'		=>	'200',
							'msg'		=>	'操作成功',
							);						
			}else{
				$result		= array(
							'code'		=>	'300',
							'msg'		=>	'删除失败',
							);
			}
		}while(FALSE);	
		
		die(json_encode($result));    	
    }
	
	
	public function uploadToCDNAction() {
        $files	= $this->getFiles('upfile');
		if( $files!=NULL && $files['size']>0 ){
			$uploader  = new FileUploader();
			$files     = $uploader->getFile('upfile');
            if(!$files){
				$result	=	array(
									'code'	=>	'300',
									'msg'	=>	'文件上传失败',
							);
				json($result);
			}
            if($files->getSize()==0){
				$result	=	array(
									'code'	=>	'300',
									'msg'	=>	'文件大小为0',
							);
				json($result);
            }
			$config	= Yaf_Registry::get('config');
            if (!$files->checkExts($config['application']['upfileExts'])){				
            	//throw new Exception('文件类型出错, 只允许'.$config['application']['upfileExts']);
				$result	=	array(
									'code'	=>	'300',
									'msg'	=>	'文件类型不对',
							);
				json($result);
            }
			if (!$files->checkSize($config['application']['upfileSize'])){
            	//throw new Exception('文件大小出错, 不允许超过.'.$config['application']['upfileSize'].'字节');
				$result	=	array(
									'code'	=>	'300',
									'msg'	=>	'文件大小出错',
							);
				json($result);
            }
			$cdnfilename = 'Images-t' . time().rand(100,999) . '.' . $files->getExt();
			if( $image = $this->uploadToCDN($files->getTmpName(), $cdnfileName) ){
				$rows	=	array(
									"originalName" 	=> $files->getFilename() ,
									"name" 			=> $cdnfilename ,
									"url" 			=> $image ,
									"size" 			=> $files->getSize() ,
									"type" 			=> $files->getMimeType() ,
									"state" 		=> 'SUCCESS'
							);
			}else{
				$rows	=	array(
									'code'	=>	'300',
									'msg'	=>	'文件上传失败',
							);
			}
		}
		die( json_encode($rows) );
    }
	
	/**
     * deal imgage upload
     */
    private function _uploadPicture($upfile) {
        do {
            $uploader  = new FileUploader();
            $files     = $uploader->getFile($upfile);
            if(!$files) break; 
            if($files->getSize()==0){
				//throw new Exception('file size is zero.');
				break;
            }
			$config	= Yaf_Registry::get('config');
            if (!$files->checkExts($config['application']['upfileExts'])){				
            	//throw new Exception('文件类型出错, 只允许'.$config['application']['upfileExts']);
                break;
            }
			if (!$files->checkSize($config['application']['upfileSize'])){
            	//throw new Exception('文件大小出错, 不允许超过.'.$config['application']['upfileSize'].'字节');
                break;
            }
			
			$filename = 'home-t' . time() . '.' . $files->getExt();
			$descdir  = $config['application']['uploadpath'] . '/Home/';
			if( !is_dir($descdir) ){ mkdir($descdir); }
			$realpath = $descdir . $filename;			
			if($files->move($realpath)){				
				return str_replace('./', '/', $realpath);
			}
        }while(false);
        
        return false;
    }
	
	/***JS直接上传文件到七牛cdn取token***/
	public function uptokenAction(){
			// 需要填写你的 Access Key 和 Secret Key
			$accessKey = $this->config['application']['cdn']['accessKey'];
			$secretKey = $this->config['application']['cdn']['secretKey'];

			// 构建鉴权对象			
			$auth = new \Qiniu\Auth($accessKey, $secretKey);
			// 要上传的空间
			$bucket = $this->config['application']['cdn']['bucket'];

			// 生成上传 Token
			$token = $auth->uploadToken($bucket);			
			die('{"uptoken":"' . $token . '"}');
	}
	/***PHP上传文件到七牛cdn***/
	public function uploadToCDN($filePath, $cdnfileName){					
			// 需要填写你的 Access Key 和 Secret Key
			$accessKey = $this->config['application']['cdn']['accessKey'];
			$secretKey = $this->config['application']['cdn']['secretKey'];

			// 构建鉴权对象
			$auth = new \Qiniu\Auth($accessKey, $secretKey);
			// 要上传的空间
			$bucket = $this->config['application']['cdn']['bucket'];
			
			// 生成上传 Token
			$token = $auth->uploadToken($bucket);

			// 上传到七牛后保存的文件名
			$key = $cdnfileName;

			// 初始化 UploadManager 对象并进行文件的上传
			$uploadMgr = new \Qiniu\Storage\UploadManager;

			// 调用 UploadManager 的 putFile 方法进行文件的上传
			list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
			if ($err !== null) {
				return false;
			} else {
				return $this->config['application']['cdn']['url'] . $ret['key'];
			}
	}

	
}

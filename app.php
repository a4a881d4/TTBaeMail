<?php
/*** 
Baidu Mail extenstion info block  
##name 百度邮件 
##folder_name bae_mail
##author a4a881d4
##email a4a881d4@163.com
##reversion 1
##desp 通过百度消息队列发送邮件 
##update_url 
##reverison_url 
***/

// 检查并创建数据库
if( !mysql_query("SHOW COLUMNS FROM `bae_mail`",db()) )
{
	// table not exists
	// create it
	run_sql("CREATE TABLE IF NOT EXISTS `bae_mail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(256) NOT NULL,
  `data` text NOT NULL,
  `timeline` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8; ");

	if( $data = get_data("SELECT `id` FROM `user` ") )
	{
		$sql = "REPLACE INTO `keyvalue` ( `key` , `value` ) VALUES ";
		foreach( $data as $item )
		{
			$v[] = "( 'baem_usettings_" . intval($item['id']) . "' , 1 )";
		}

		$sql = $sql . join( ' , ' , $v );
		run_sql( $sql );
	}

	kset( 'baem_not_online' , 1 );
}

// 添加邮件设置菜单
add_action( 'UI_USERMENU_ADMIN_LAST' , 'bae_mail_menu_list');
function bae_mail_menu_list()
{
	?><li><a href="javascript:show_float_box( '百度消息队列设置' , '?c=plugin&a=bae_mail' );void(0);">百度消息队列设置</a></li>
	<?php 	 	
} 

add_action( 'PLUGIN_BAE_MAIL' , 'plugin_bae_mail');
function  plugin_bae_mail()
{

	$data['baem_on'] = kget('baem_on');
	$data['baem_qname'] = kget('baem_qname');
	$data['baem_not_online'] = kget('baem_not_online');
	return render( $data , 'ajax' , 'plugin' , 'bae_mail' ); 
}

add_action( 'PLUGIN_BAE_MAIL_SAVE' , 'plugin_bae_mail_save');
function  plugin_bae_mail_save()
{
	$baem_on = intval(t(v('baem_on')));
	$baem_qname = z(t(v('baem_qname')));
	$baem_not_online = z(t(v('baem_not_online')));

	if( strlen( $baem_on ) < 1 
		|| strlen( $baem_qname ) < 1 

	) return ajax_echo('设置内容不能为空');

	kset('baem_on' , $baem_on);	
	kset('baem_qname' , $baem_qname);	
	kset('baem_not_online' , $baem_not_online);	

	return ajax_echo('设置已保存<script>setTimeout( close_float_box, 500)</script>');

}

add_action( 'UI_INBOX_LIST_BEFORE' , 'bae_mail_css' );
function  bae_mail_css()
{
	?>
	<style type="text/css">
	#baem_settings.on
	{
		background-color:rgb(153, 153, 153);
	}
	</style>
	<?php
}

add_action( 'UI_INBOX_SCRIPT_LAST' , 'bae_mail_js' );
function bae_mail_js()
{
	if( intval(kget('baem_on')) != 1 ) return false;
	?>
	function bae_mail_settings_toggle()
	{
		if( $('#baem_settings').hasClass('on') )
		{
			$.post( '?c=plugin&a=bae_mset&on=0' , {} , function()
			{
				noty(
				{
					text:'你将不再收到百度的邮件通知',
					timeout:1000,
					layout:'topRight',
					type:'warning'
				});
				$('#baem_settings').removeClass('on');
			});
		}
		else
		{
			$.post( '?c=plugin&a=bae_mset&on=1' , {} , function()
			{
				noty(
				{
					text:'你将会收到百度的邮件通知',
					timeout:1000,
					layout:'topRight',
					type:'success'
				});
				$('#baem_settings').addClass('on');
			});
		}
	}
	<?php
}

add_action( 'UI_COMMON_SCRIPT' , 'check_bae_mail_script' );
function check_bae_mail_script()
{
	if( intval(kget('baem_on')) != 1 ) return false;
	?>
	var sending_mail = false;
	var mail_noty = null ;

	function baem_test()
	{
		var url = '?c=plugin&a=test_bae_mail' ;
		var params = {};

		var params = {};
		$.each( $('#baem_form').serializeArray(), function(index,value) 
		{
			params[value.name] = value.value;
		});

		$.post( url , params , function( data )
		{
			var data_obj = $.parseJSON( data );
			if( data_obj.err_code == 0 )
			{
				mail_noty = noty(
				{
						text:'已经向'+ data_obj.data.mail_sent +'发送了邮件，请登入邮箱检查。如果邮件在垃圾箱，请将发件人加入白名单。',
						layout:'topRight',
				});
			}
			else
			{
				return alert('发送失败，请检查配置想是否填写完整，错误信息'+data_obj.err_msg);
			}
		});	
	}


	function check_bae_mail()
	{
		var url = '?c=plugin&a=check_bae_mail' ;
	
		var params = {};
		$.post( url , params , function( data )
		{
			var data_obj = $.parseJSON( data );
			if( data_obj.err_code == 0 )
			{
				if( data_obj.data.to_send && parseInt( data_obj.data.to_send ) > 0 )
				{
					if( mail_noty != null )
					{
						mail_noty.setText('正在发送队列中的邮件-剩余'+parseInt( data_obj.data.to_send )+'封');
					}
					else
					mail_noty = noty(
					{
						text:'正在发送队列中的邮件-剩余'+parseInt( data_obj.data.to_send )+'封',
						layout:'topRight',
					});

					sending_mail = true;
					check_bae_mail();
				}
				else
				{
					if( sending_mail )
					{
						sending_mail = false;
						mail_noty.close();
					}
				}
			}
		});	
	}

	setTimeout( check_bae_mail , 9000 );
	setInterval( check_bae_mail , 120000 );

	<?php
}

// test_mail

function bae_send_mail ( $queueName, $subject, $message, $address )
{
    require_once ( dirname(__FILE__) . DS . 'Bcms.class.php' );
  	include_once( AROOT .'controller' . DS . 'api.class.php');
    $bcms = new Bcms ( ) ;
    $ret = $bcms->mail ( $queueName, $message, $address, array( Bcms::MAIL_SUBJECT => $subject) ) ;
    if( false === $ret ) {
    	apiController::send_error( LR_API_ARGS_ERROR , $bcms->errmsg() );
    }
    return $ret;
}

add_action( 'PLUGIN_TEST_BAE_MAIL' , 'plugin_test_bae_mail' );
function plugin_test_bae_mail()
{
	include_once( AROOT .'model' . DS . 'api.function.php');
	include_once( AROOT .'controller' . DS . 'api.class.php');
	
	$baem_on = intval(t(v('baem_on')));
	$baem_qname = z(t(v('baem_qname')));
	

	if( strlen( $baem_qname ) < 1 )
		return apiController::send_error( LR_API_ARGS_ERROR , 'SMTP ARGS ERROR ' );


	if($user = get_user_info_by_id( uid() ))
	{
		session_write_close();
		
		$ret = bae_send_mail( $baem_qname 
			,'来自TeamToy的测试邮件 '.date("Y-m-d H:i") , '如果您收到这封邮件说明您在SMTP中的邮件配置是正确的；如果您在垃圾邮箱找到这封邮件，请将发件人加入白名单。' 
			, array($user['email'])
		);
		if( false === $ret )
		{
			
		} else {
			return apiController::send_result( array( 'mail_sent' => $user['email'] ) );
		}
	}

	return apiController::send_error( 200010 , 'SMTP ERROR ' );
	
}

add_action( 'PLUGIN_CHECK_BAE_MAIL' , 'plugin_check_bae_mail' );
function plugin_check_bae_mail()
{
	if( intval(kget('baem_on')) != 1 ) return false;
	$sql = "SELECT * FROM `bae_mail` WHERE `timeline` > '" . date("Y-m-d H:i:s" , strtotime( "-1 hour" ) ) . "' LIMIT 1";
	if( $line = get_line( $sql ) )
	{
		session_write_close();
		$info = unserialize( $line['data'] );
		if( bae_send_mail( kget('baem_qname'),  $info['subject'] , $info['body'], array($info['to']) ))
		{
			$sql = "DELETE FROM `bae_mail` WHERE `id` = '" . intval( $line['id'] ) . "' LIMIT 1";
		}
		else
		{
			$sql = "UPDATE `bae_mail` SET `timeline` = '" . date("Y-m-d H:i:s" , strtotime("-2 hours")) . "' LIMIT 1 ";
		}

		run_sql( $sql );
	}

	include_once( AROOT .'controller' . DS . 'api.class.php');
	if( db_errno() != 0  ) apiController::send_error( LR_API_DB_ERROR , 'DATABASE ERROR ' . db_error() );
	return apiController::send_result( array('to_send'=>get_var("SELECT COUNT(*) FROM `bae_mail` WHERE `timeline` > '" . date("Y-m-d H:i:s" , strtotime( "-1 hour" ) ) . "' ")) );
}


add_action( 'SEND_NOTICE_AFTER' , 'send_notice_bae_mail' );
function send_notice_bae_mail( $data )
{
	if( intval(kget('baem_on')) != 1 ) return false;
	if( intval(kget('baem_usettings_'.$data['uid'])) == 1  )
	{
		// 未设置，或者设置为接受

		// 检查是否在线
		// 只有不在线的时候发送邮件通知
		$send = true;

		if( intval( kget('baem_not_online') ) == 1 && is_online( $data['uid'] ) )
			$send = false;


		$baem_qname = kget('baem_qname');

		if( $send )
		{
			$user = get_user_info_by_id( $data['uid'] );
			$subject = c('site_name').' 邮件通知 - ' . mb_strimwidth( $data['content'] , 0 , 20 , '...' , 'UTF-8' );
			$body = $data['content'] . ' - <a href="' . c('site_url') . '/?c=inbox">点击这里查看详情</a>';
			$dd = array();
			
//			$ret = bae_send_mail( $baem_qname, $subject, $body, array($user['email']) );
/*			if( false === $ret )
				$dd['subject'] = 'send mail wrony' . $baem_qname . $subject . $body;
			else
*/			
			$dd['subject'] = c('site_name').' 邮件通知 - ' . mb_strimwidth( $data['content'] , 0 , 20 , '...' , 'UTF-8' );
			

			$dd['to'] = $email = $user['email'];
			$dd['body'] = $data['content'] . ' - <a href="' . c('site_url') . '/?c=inbox">点击这里查看详情</a>';
			
			$sql = "INSERT INTO `bae_mail` ( `email` , `data` , `timeline` ) VALUES ( '" . s( $email ) . "' , '" . s(serialize($dd)) . "' , '" . s(date("Y-m-d H:i:s")) . "' )";
			run_sql( $sql );

		}

		

	}

	
}

add_action( 'PLUGIN_BAE_MSET' , 'plugin_bae_mset' );
function plugin_bae_mset()
{
	if( intval(v('on')) == 1 ) kset(  'baem_usettings_'.uid() , 1 );
	else kset(  'baem_usettings_'.uid() , 0 );
}

add_action( 'UI_INBOX_SETTINGS_LAST' , 'bae_mail_inbox_icon');
function bae_mail_inbox_icon()
{
	if( intval(kget('baem_on')) == 1 )
	{
		if( intval(kget('baem_usettings_'.uid())) == 1 )
		{
			?>
			<li id="baem_settings" class="on"><a href="javascript:bae_mail_settings_toggle();void(0);" title="邮件通知" ><img src="<?=image('settings.btn.email.png')?>"/></a></li>
			<?php
		}
		else
		{
			?>
			<li id="baem_settings" ><a href="javascript:bae_mail_settings_toggle();void(0);" title="邮件通知" ><img src="<?=image('settings.btn.email.png')?>"/></a></li>
			<?php
		}
	}
}
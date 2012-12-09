window.onload = function(){
	var j=jQuery;
	if(j(".ath-selector").size()>0) {
		/* Post edit meta page */
		var selOnChange = function(){
			if(this.options[this.selectedIndex].value==-1) {
				j(".new-ath-box",this.parentNode).css("display","block");
				j(".cloak").css("display","block");
			}
		};
		var clsOnClick=function(){
			j(this.parentNode).css("display","none");
			j(".cloak").css("display","none");
			var name = j("input[name='ath-fname[]']",this.parentNode).val() + " " + j("input[name='ath-lname[]']",this.parentNode).val();
			if(name.replace(/^\s+|\s+$/g, "").length>0)
			j(this.parentNode).siblings("select").children("option[value='-1']").text(name);
		};
		var akillOnClick=function() {
			j(this).parent().remove();
		}
		var globalEventReg=function(g) {
			if(typeof(g)=="undefined") g = document.body;

			j(".ath-selector",g).change(selOnChange).load(selOnChange);
			j(".ath-new-winclose",g).click(clsOnClick);
			j(".ath-field-kill",g).click(akillOnClick);
		};
		
		j("#add-ath-btn").click(function() {
			var t = j(".ath-field:first").clone(true, true);
			/* Re-register events + clear values */
			j("input",t).val("");
			j("[name='ath-edit-ids[]']",t).val(-1);
			j(t).css("display","block");
			globalEventReg(t);
			/* Add */
			j(this).before(t);
		});
		globalEventReg();
    } else {
		/* Author profiles page */
		if(typeof(window.authinfo)=="undefined") window.authinfo = [];
		var ainfo = window.authinfo, ninfo = {"-1":[]};
		var edit_id = 1;
		
		var encodeAuthField = function(info) {
			j("#ath-info-encoded").val(JSON.stringify(info));
		};
		
		var addAuthToTable = function(id, info) {
			var ap = j(".new-ath-box:first").clone(true,true).css("display","none");
			var al = j(".ath-entry:first").clone(true,true).css("display","block");
			for(var k in info)
				j("[name='ath-"+k+"[]']", ap).val(info[k]);

			j("[name='ath-edit-ids[]']",ap).val(id);
			j(".ath-name",al).text(info['fname']+" "+info['lname']);
			j(".ath-id",al).val(id);
			j("#ath-table").append(j(al).append(ap));
			return al;
		};
		
		j(".ath-create-btn").click(function() {
			var t = addAuthToTable(-1, {fname:"New", lname:"Author"});
			j(".new-ath-box",t).css("display","block");
			j(".cloak").css("display","block");
		});
		
		j(".ath-edit-btn").click(function() {
			var t = j(".new-ath-box",this.parentNode.parentNode);
			j(t).css("display","block");
			j(".cloak").css("display","block");
		});

		j(".ath-del-btn").click(function() {
			var ent = j(this.parentNode.parentNode), id = j(".ath-id",ent).val(), eid = j("[name='ath-edit-ids[]']", ent).val();
			j(ent).remove();
			if(id==-1) {
				for(var i in ninfo[-1])
					if(ninfo[-1][i]['edit-ids'] == eid) {
						delete ninfo[-1][i];
						break;
					}
			}else {
				ninfo[id] = {"deleteflag":true};
			}
			encodeAuthField(ninfo);
		});
		
		j(".ath-new-winclose").click(function() {
			var infob = this.parentNode, id = j(".ath-id", infob.parentNode).val();
			j(infob).removeAttr("style");
			/* Update all info */
			var info = {};
		   
			j("[name^='ath-']", infob).each(function() {
				var key = j(this).attr("name");
				key = key.replace("ath-","").replace("[]","");
				if(key=="edit-ids")
					j(this).val(edit_id++);
				info[key] = j(this).val();
			});

			j(".ath-name", infob.parentNode).text(info['fname']+" "+info['lname']);

			if(id == -1) {
				ninfo["-1"].push(info);
			}else {
				ninfo[id] = info;
			}
			encodeAuthField(ninfo);
			
			j(".cloak").css("display","none");
		});
		
		j("#next-page-btn").click(function() {
			var mid = -1;
			for(var id in ainfo)
				if(id > mid)
					mid = id;
			window.location.href = j.param.querystring(window.location.href, 'min_id='+(+mid+1));
		});
		
		j("#last-page-btn").click(function() {
			window.location.href = j.param.querystring(window.location.href, 'min_id='+window.authlastid);
		});
		
		/* Add info */
		for(var i in ainfo) {
			addAuthToTable(i, ainfo[i]);
		}
    }
};
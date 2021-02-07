
/* 


    // enable codeigniter 4 bootstrap 4 ajax buttons
    $( "#controller_table .btn-ajax" ).bs4ajaxbutton({
        iconposition: 'replace',
        baseurl: "https://www.strivers.de/ci4/public",
        module: "admin",
        controller: "api/controller",
    });

    // enable codeigniter 4 bootstrap 4 ajax buttons
    $( "#addcontroller .btn-ajax" ).bs4ajaxbutton({
        iconposition: 'replace',
        baseurl: "https://www.strivers.de/ci4/public",
        module: "admin",
        formid: "#addcontroller",
        controller: "api/controller",
    });

    // enable codeigniter 4 bootstrap 4 ajax buttons
    $( "#db_table .btn-ajax" ).bs4ajaxbutton({
        iconposition: 'replace',
        baseurl: "https://www.strivers.de/ci4/public",
        module: "admin/api",
        controller: "database",
    });


*/


(function ( $ ) {

	constructor = function(selector, context) {
	  // The jQuery object is actually just the init constructor 'enhanced'
	  //return new jQuery.fn.init(selector, context);
	};

    $.fn.bs4ajaxbutton = function( options ) {
  
        // This is the easiest way to have default options.
        var settings = $.extend({
            // default options
            method    : "POST",
            data      : "",
            formid    : "",
            baseurl   : "",
            module    : "",
            controller: "",
            action    : "",
            id        : "",
            success_message : "Datensatz mit der ID <b>{id}</b> konnte erfolgreich gespeichert werden.",
            fail_message : "Datensatz mit der ID <b>{id}</b> konnte nicht erstellt werden!",
            autoconfig: true,
            addicon   : true,
            iconposition: 'prepend', //prepend, append, replace
            removetr  : false, // remove tr if delete is done
            reload : false, // reload page if done
            done: function( msg ) {
				console.log( "done" );
				console.log( msg );
				if (settings.removetr) { settings.button.closest("tr").remove(); };
				if (settings.reload) { location.reload(); }
				bs4alert( success_message.replace("{id}", id), 'success', 3000);
			},
            fail: function( msg ) {
				console.log( "fail" );
				console.log( msg );
				console.log( $( this) );
				$( this ).addClass( "btn-warning" );
				bs4alert( fail_message.replace("{id}", id), 'danger', 3000);
			},
            always: function( msg ) {
				console.log( "always" );
				console.log( msg );
			},
            delete_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-trash2" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3.18 4l1.528 9.164a1 1 0 0 0 .986.836h4.612a1 1 0 0 0 .986-.836L12.82 4H3.18zm.541 9.329A2 2 0 0 0 5.694 15h4.612a2 2 0 0 0 1.973-1.671L14 3H2l1.721 10.329z"></path><path d="M14 3c0 1.105-2.686 2-6 2s-6-.895-6-2 2.686-2 6-2 6 .895 6 2z"></path><path fill-rule="evenodd" d="M12.9 3c-.18-.14-.497-.307-.974-.466C10.967 2.214 9.58 2 8 2s-2.968.215-3.926.534c-.477.16-.795.327-.975.466.18.14.498.307.975.466C5.032 3.786 6.42 4 8 4s2.967-.215 3.926-.534c.477-.16.795-.327.975-.466zM8 5c3.314 0 6-.895 6-2s-2.686-2-6-2-6 .895-6 2 2.686 2 6 2z"></path></svg>',
           	edit_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-pencil" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M11.293 1.293a1 1 0 0 1 1.414 0l2 2a1 1 0 0 1 0 1.414l-9 9a1 1 0 0 1-.39.242l-3 1a1 1 0 0 1-1.266-1.265l1-3a1 1 0 0 1 .242-.391l9-9zM12 2l2 2-9 9-3 1 1-3 9-9z"></path><path fill-rule="evenodd" d="M12.146 6.354l-2.5-2.5.708-.708 2.5 2.5-.707.708zM3 10v.5a.5.5 0 0 0 .5.5H4v.5a.5.5 0 0 0 .5.5H5v.5a.5.5 0 0 0 .5.5H6v-1.5a.5.5 0 0 0-.5-.5H5v-.5a.5.5 0 0 0-.5-.5H3z"></path></svg>',
           	new_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-plus" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 3.5a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5H4a.5.5 0 0 1 0-1h3.5V4a.5.5 0 0 1 .5-.5z"/><path fill-rule="evenodd" d="M7.5 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1H8.5V12a.5.5 0 0 1-1 0V8z"/></svg>',
           	default_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-app" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M11 2H5a3 3 0 0 0-3 3v6a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V5a3 3 0 0 0-3-3zM5 1a4 4 0 0 0-4 4v6a4 4 0 0 0 4 4h6a4 4 0 0 0 4-4V5a4 4 0 0 0-4-4H5z"/></svg>',
			update_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-box-arrow-in-down" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.646 8.146a.5.5 0 0 1 .708 0L8 10.793l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/><path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-1 0v-9A.5.5 0 0 1 8 1z"/><path fill-rule="evenodd" d="M1.5 13.5A1.5 1.5 0 0 0 3 15h10a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 13 4h-1.5a.5.5 0 0 0 0 1H13a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5v-8A.5.5 0 0 1 3 5h1.5a.5.5 0 0 0 0-1H3a1.5 1.5 0 0 0-1.5 1.5v8z"/></svg>',
			save_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-box-arrow-in-down" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.646 8.146a.5.5 0 0 1 .708 0L8 10.793l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/><path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-1 0v-9A.5.5 0 0 1 8 1z"/><path fill-rule="evenodd" d="M1.5 13.5A1.5 1.5 0 0 0 3 15h10a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 13 4h-1.5a.5.5 0 0 0 0 1H13a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5v-8A.5.5 0 0 1 3 5h1.5a.5.5 0 0 0 0-1H3a1.5 1.5 0 0 0-1.5 1.5v8z"/></svg>',
			index_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-list" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M2.5 11.5A.5.5 0 0 1 3 11h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 7h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 3h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>',
			show_icon: '<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-box-arrow-up-right" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M1.5 13A1.5 1.5 0 0 0 3 14.5h8a1.5 1.5 0 0 0 1.5-1.5V9a.5.5 0 0 0-1 0v4a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 0 0-1H3A1.5 1.5 0 0 0 1.5 5v8zm7-11a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 .5.5v5a.5.5 0 0 1-1 0V2.5H9a.5.5 0 0 1-.5-.5z"/><path fill-rule="evenodd" d="M14.354 1.646a.5.5 0 0 1 0 .708l-8 8a.5.5 0 0 1-.708-.708l8-8a.5.5 0 0 1 .708 0z"/></svg>',
			
        }, options );

		bs4alert = function( message, color, milliseconds ) {


		  var id = Math.round(Math.random()*10000);

			$('#bs4alerts')
				.append('<div id="alertdiv'+id+'" class="alert alert-' + color
					+ '" role="alert"><a class="close" data-dismiss="alert">×</a><span>' + message 
					+ '</span></div>');
			if (milliseconds>0) {
				  setTimeout( function() {
					  $("#alertdiv"+id).fadeTo(500, 0).slideUp(500, function () { $(this).remove() });
				  }, milliseconds );
			   }
		};

        // autoconfig
        if (settings.autoconfig) {
        	console.log("Autoconfig");
        	$(this).each(function( ) {
        		button = $(this);
				switch(true) {
					case button.hasClass("btn-ajax-new"):
					    button.addClass("btn-outline-primary");
					    var icon = settings.new_icon;
					    break;
					case button.hasClass("btn-ajax-create"):
					    button.addClass("btn-outline-primary");
					    var icon = settings.save_icon;
					    break;
					case button.hasClass("btn-ajax-index"):
					    button.addClass("btn-outline-secondary");
					    var icon = settings.index_icon;
					    break;
					case button.hasClass("btn-ajax-show"):
					    button.addClass("btn-outline-info");
					    var icon = settings.show_icon;
					    break;
					case button.hasClass("btn-ajax-update"):
					    button.addClass("btn-outline-primary");
					    var icon = settings.update_icon;
					    break;
					case button.hasClass("btn-ajax-delete"):
					    button.addClass("btn-outline-danger");
					    var icon = settings.delete_icon;
					    break;
					case button.hasClass("btn-ajax-edit"):
					    button.addClass("btn-outline-secondary");
					    var icon = settings.edit_icon;
					    break;
					default:
					    button.addClass("btn-outline-info");
					    var icon = settings.default_icon;
					    break;
				}

				if (settings.addicon) {
					if (settings.iconposition=='prepend') { button.prepend(icon); } 
					else if (settings.iconposition=='replace') {button.html(icon); }
					else { button.append(icon); };
				};

			});
		}

        // add click event
        this.click(function() {

			button = $(this);
			settings.button = button;
console.log("click")
	        // autoconfig
	        if (settings.autoconfig) {
	        	console.log("Autoconfig on click");
	        	$(this).each(function( ) {
	        		button = $(this);

					if (button.data('reload')) {
						settings.reload=true;
					}

					switch(true) {

						// NEW: receives an new empty entity
						// Create New Button
						// $routes->get('controller/new', 'controller::new');
						case button.hasClass("btn-ajax-new"):
						    settings.method = "get";
						    console.log("new");
						    break;

						// CREATE: creates a new entity after receiving an filling a new entity
						// Save Button
						// $routes->post('controller', 'controller::create');
						case button.hasClass("btn-ajax-create"):
							if (button.data('id')) {
							    settings.method = "put";
							    settings.action = "{id}";
							} else {
							    settings.method = "post";
							}
						    break;

						// INDEX: receives all entities. filter an sort informations send in parameters
						// $routes->get('controller','controller::index');
						case button.hasClass("btn-ajax-index"):
						    settings.method = "get";
						    break;

						// SHOW: receives an entity by id
						// $routes->get('controller/(:segment)',      'controller::show/$1');
						case button.hasClass("btn-ajax-show"):
						    settings.method = "get";
						    settings.action = "{id}";
						    break;

						// EDIT: receives an new empty entity
						// $routes->get('controller/(:segment)/edit', 'controller::edit/$1');       
						case button.hasClass("btn-ajax-edit"):
						    //button.addClass("btn-outline-warning");
						    settings.method = "post";
						    settings.action = "{id}/edit";
						    break;

						// UPDATE: updates an existing record
						// $routes->put('controller/(:segment)', 'controller::update/$1');
						case button.hasClass("btn-ajax-update"):
							if (button.data('id')) {
							    settings.method = "put";
							    settings.action = "{id}";
							} else {
							    settings.method = "post";
							}
						    break;

						// DELETE: deletes an existing record
						// Delete Button
						// $routes->delete('controller/(:segment)',   'controller::delete/$1');
						case button.hasClass("btn-ajax-delete"):
						    settings.method = "delete";
						    settings.action = "{id}";
						    settings.removetr = true;
						    console.log("delete");
						    break;
							
							// INDEX is default
						default:
						    settings.method = "get";
						    console.log("index");
						    break;
					}
				});
			}

			method     = button.data('method')     ? button.data('method')     : settings.method;
			baseurl    = button.data('baseurl')    ? button.data('baseurl')    : settings.baseurl;
			module     = button.data('module')     ? button.data('module')     : settings.module;
			controller = button.data('controller') ? button.data('controller') : settings.controller;
			action     = button.data('action')     ? button.data('action')     : settings.action;
			id         = button.data('id')         ? button.data('id')         : settings.id;
			url        = baseurl+"/"+module+"/"+controller+"/"+action;
			url        = url.replace("{id}", id).replace(/^\/|\/$/g, '');
			formid     = button.data('formid')	   ? button.data('formid')     : settings.formid;
			data       = button.data('data')       ? button.data('data')       : settings.data;
			data       = (formid!="")			   ? $(formid).serialize()     : data;
			href       = button.data('href')	   ? button.data('href')       : null;


			success_message = button.data('successmessage') ? button.data('successmessage')  : settings.success_message;
			fail_message = button.data('failmessage')       ? button.data('failmessage')     : settings.fail_message;
			done = button.data('done') ? button.data('done') : settings.done;
			fail = button.data('fail') ? button.data('fail') : settings.fail;
			always = button.data('always') ? button.data('always') : settings.always;


			console.log( "Method: "+method+" ID: "+button.data('id') );
			console.log( "URL: "+url+" Data: "+data );


			if (method=='delete') {
				showConfirmDelete('Löschen','Soll der Datensatz wirklich gelöscht werden?',function () { 

					result = $.ajax({
						method: method,
						url: url,
						data: data
					})
					.done( done )
					.fail( fail )
					.always( always );

				});
			} else if (href) {
				console.log(href);
				window.location.href = href;
			} else {

				result = $.ajax({
					method: method,
					url: url,
					data: data
				})
				.done( done )
				.fail( fail )
				.always( always );
			}

        });
  
    };

	/*
	Function: bs4ajaxcheckbox



	Example:

        checkbox = $("#user_table tbody tr input:checkbox").bs4ajaxcheckbox({
            baseurl: "<?= base_url('/Idoit/userspermissions') ?>",
            ida : "permission_id",
            idb : "user_id",
            debug: true,
        })

	*/
	$.fn.bs4ajaxcheckbox = function( options ) {

        // This is the easiest way to have default options.
        var settings = $.extend({

            // default options
            baseurl : "",
            ida : "ida",
            idb : "idb",
            debug : false,

            done: function( msg ) {
				console.log( "done" );
				console.log( msg );
				if (settings.removetr) { settings.button.closest("tr").remove(); };
				if (settings.reload) { location.reload(); }
				bs4alert( success_message.replace("{id}", id), 'success', 3000);
			},
            fail: function( msg ) {
				console.log( "fail" );
				console.log( msg );
				console.log( $( this) );
				$( this ).addClass( "btn-warning" );
				bs4alert( fail_message.replace("{id}", id), 'danger', 3000);
			},
            always: function( msg ) {
				console.log( "always" );
				console.log( msg );
			},
			
        }, options );

        log = function ( msg ) {
	        if (settings.debug) {
	        	if (typeof msg === 'object' && msg !== null) {
	        		console.log(msg)
	        	} else {
	        		console.log("bs4ajaxcheckbox: "+msg);
	        	}
	        }
        }

        if (this.length > 1) {
            this.each(function() { 

            });
        }

this.each(function() { 
        // add change event
console.log(this);

        $(this).change(function() {

        	log("===== CHANGE =====")

            var method = $(this).is(":checked") ? "POST" : "DELETE"; 
			log("Method = "+method)

            data = {
                [settings.ida] : $(this).data(settings.ida),
                [settings.idb] : $(this).data(settings.idb),
            } 
			log("Data = ")
			log(data)

            url = settings.baseurl;
            if (method=="DELETE") {
                //url = url+"/"+$(this).data(settings.ida)+"/user/"+$(this).data(settings.idb)
                url = url+"/user/"+$(this).data(settings.idb)
            }
            log("Url = "+url)

            $.ajax({
                url: url,
                method: method,
                data: data,
                dataType: 'json'
            })
            .done(function(data) {
            	log("Done = ")
                log(data)
            })
            .fail(function(data) {
            	log("Fail = ")
                log(data)
            });        
        });        
 });

        return this;
	};

	$.fn.bs4ajaxalert = function( options ) {

        // This is the easiest way to have default options.
        var settings = $.extend({
            // default options
            message   : "",
            color     : "",
            milliseconds   : "",
            module    : "",
            controller: "",
            action    : "",
            id        : "",
            success_message : "Datensatz mit der ID <b>{id}</b> konnte erfolgreich gespeichert werden.",
            fail_message : "Datensatz mit der ID <b>{id}</b> konnte nicht erstellt werden!",
            autoconfig: true,
            addicon   : true,
            iconposition: 'prepend', //prepend, append, replace
            removetr  : false, // remove tr if delete is done
            reload : false, // reload page if done

        }, options );

		bs4alert = function( message, color, milliseconds ) {


		  var id = Math.round(Math.random()*10000);

			$('#bs4alerts')
				.append('<div id="alertdiv'+id+'" class="alert alert-' + color
					+ '" role="alert"><a class="close" data-dismiss="alert">×</a><span>' + message 
					+ '</span></div>');
			if (milliseconds>0) {
				  setTimeout( function() {
					  $("#alertdiv"+id).fadeTo(500, 0).slideUp(500, function () { $(this).remove() });
				  }, milliseconds );
			   }
		};

		setTimeout = function( milliseconds ) {
			window.setTimeout(function () {
				$(".alert")
				.fadeTo(milliseconds, 0)
				.slideUp(milliseconds, function () {
					$(this).remove();
				});
			}, 2000);	
		};	
	};

	$.fn.bs4tabs = function( options ) {

        // This is the easiest way to have default options.
        var settings = $.extend({

            // default options
            debug : true,

			
        }, options );

        log = function ( msg ) {
	        if (settings.debug) {
	        	if (typeof msg === 'object' && msg !== null) {
	        		console.log(msg)
	        	} else {
	        		console.log("bs4tabs: "+msg);
	        	}
	        }
        }

        log("Start")    

	    $(this).find("a").click(function(e) {
	    	log("Hash = "+$(this).attr("id"))
	        window.location.hash = $(this).attr("id")
	        $(this).blur()
	        e.preventDefault();
	    });

	    var v = "#" + window.location.hash.substr(1);
	    if (v!='#') {
	    	log("Show = "+v)
	        $(v).tab("show").blur()
	    }  

        return this;
	};

	$.fn.bs4table = function( options ) {

		// TableID
		var table = $(this).attr("id")
        // This is the easiest way to have default options.
        var settings = $.extend({

            // default options
            table: table,
            searchfield: $("input:text[data-table='"+table+"']"),
            searchcheckbox: $("input:checkbox[data-table='"+table+"']"),
            debug : true,

			
        }, options );
        
        log = function ( msg ) {
	        if (settings.debug) {
	        	if (typeof msg === 'object' && msg !== null) {
	        		console.log(msg)
	        	} else {
	        		console.log("bs4table: "+msg);
	        	}
	        }
        }

        
        log("Start: "+table)    

	    /* Handle SearchField */
	    $(settings.searchfield).on("keyup", function() {
	        toggleRow(settings);
	    });


	    /* Handle Checked Checkbox */
	    $(settings.searchcheckbox).on( "click", function() {  
	        toggleRow(settings);
	    });

	    toggleRow = function (settings) {

	        var value = $(settings.searchfield).val().toLowerCase();
	        var filterchecked = $(settings.searchcheckbox).is(":checked");

	        log("Table: "+settings.table+" Value: "+value+" Filterchecked: "+filterchecked)

	        $("#"+settings.table).find("tbody tr").filter(function() { 

	            var checked = $(this).find("input:checkbox:not(:checked)").length == 0
	            var hasvalue = $(this).text().toLowerCase().indexOf(value) > -1

	            if ( (filterchecked && !checked) || !hasvalue ) {
	                $(this).hide();
	            } else {
	                $(this).show();
	            }
	        });

	    }

        return this;
	};

}( jQuery ));




    /* Handle SearchField 
    $(".searchfield").on("keyup", function() {
        toggleRow();
    });*/


    /* Handle Checked Checkbox 
    $( ".searchcheckbox" ).on( "click", function() {  
        toggleRow();
    });*/

    /*
	    toggleRow = function () {

	        var table = $(".searchfield").data("table");
	        var value = $(".searchfield").val().toLowerCase();
	        var filterchecked = $(".searchcheckbox").is(":checked");

	        console.log("Table: "+table+" filterchecked: "+filterchecked)

	        $("#"+table+" tbody tr").filter(function() { 
	            var checked = $(this).find("input:checkbox:not(:checked)").length == 0
	            var hasvalue = $(this).text().toLowerCase().indexOf(value) > -1

	            if ( (filterchecked && !checked) || !hasvalue ) {
	                $(this).hide();
	            } else {
	                $(this).show();
	            }
	        });

	    }

*/

 

/*
	$('a[data-toggle="tab"]').on('click', function (e) {

        var parentid = $(e.target).parent().attr('id').replace('-','');
        var activeTab = $(this).attr('href');
        console.log("ParentID: "+parentid+" ActiveTab: "+activeTab);
        window.localStorage.setItem(parentid, activeTab);

    });

    var parentid = $('a[data-toggle="tab"]:first').parent().attr('id').replace('-','');
    var activeTab = window.localStorage.getItem(parentid);

    if (activeTab) {
      $('[data-toggle="tab"][href="' + activeTab + '"]').tab('show');
    }

*/
	function showConfirmDelete(header, content, func){
	    $('#msgModal .modal-title').text(header);
	    $('#msgModal .modal-body').html(content);
	    $('#msgModal').modal('show');
	    $("#msgModal button[type='submit']").on('click',function () {
	      func();
	    });
	    $('#msgModal').on('hide.bs.modal',function () {
	      $("#msgModal button[type='submit']").off();
	    });
	}

	$('[data-toggle="tooltip"]').tooltip({delay: { "show": 800, "hide": 100 }});
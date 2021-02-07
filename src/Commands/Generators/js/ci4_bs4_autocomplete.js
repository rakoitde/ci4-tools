
(function ($) {
    $.fn.bs4autocomplete = function(args) {
        return new Bs4autocomplete(this, args);
    };

    $.fn.bs4autocomplete.logging = false;
    
    Bs4autocomplete = function(element, options) {
        
        var settings = $.extend({
            version: '0.1',
            items: [],
            maxitems: 999,
            inlist: true,
            // Ajax
            method    : "POST",
            baseurl   : "",
            module    : "",
            controller: "",
            action    : "",
            data      : "",
            key_path  : "",
            val_path  : "",
            click: function( e ) {
log("Custom Click");
            },
            done: function( msg ) {
				//bs4alert( success_message.replace("{id}", id), 'success', 3000);
			},
            fail: function( msg ) {
				$( this ).addClass( "btn-warning" );
				//bs4alert( fail_message.replace("{id}", id), 'danger', 3000);
			},
            always: function( msg ) {

			},
        }, options );
       
		var method    = element.data('method')     ? element.data('method')     : settings.method;
		var baseurl   = element.data('baseurl')    ? element.data('baseurl')    : settings.baseurl;
        var module    = element.data('module')     ? element.data('module')     : settings.module;
        var controller= element.data('controller') ? element.data('controller') : settings.controller;
        var action    = element.data('action')     ? element.data('action')     : settings.action;
		var data      = element.data('data')       ? element.data('data')       : settings.data;
        var url = ""
        if (baseurl) {
			var url       = baseurl+"/"+module+"/"+controller+"/"+action;
			var url       = url.replace(/^\/|\/$/g, '')  // replace("{id}", id).
        }
		var key_path      = element.data('key_path')       ? element.data('key_path')       : settings.key_path;
		var val_path      = element.data('val_path')       ? element.data('val_path')       : settings.val_path;

		var id = $(element).attr("id")
		var tagName = $(element).prop("tagName") // TEXT, SELECT
		var values = settings.items
		var oldvalue = $(element).val()
		var ajaxresult = []


		inputelement = element;

		if (tagName == "SELECT") {

			var select_name = $(inputelement).attr("name")
			var select_id = $(inputelement).attr("id")

			var options = $(inputelement).find('option');
			var selected = $(inputelement).find('option:selected');
			var oldkey = $(selected).val()
			var oldvalue = $(selected).text()
			

			if (settings.items.length==0) {
				values = []
				var _values = $.map(options ,function(option) {
				    settings.items.push({'key': option.value, 'value': option.text}) 
					values.push(option.text)
				});
			}

			// add new input fields
			new_input_key = '<input type="hidden" id="'+select_id+'_key" value="'+oldkey+'" name="'+select_name+'" placeholder="key" >'
			new_input_value  = '<input type="text" id="'+select_id+'" value="'+oldvalue+'" class="form-control">'
			new_div = '<div class="autocomplete">'+new_input_key+new_input_value+'</div>'
			$(inputelement).before(new_div)

			// remove old select input
			$(inputelement).remove()

			inputelementkey = $("#"+select_id+"_key")
			inputelement = $("#"+select_id)

		} 

		log("Tag: "+tagName+" ID: #"+id+" oldvalue: "+oldvalue )

        if (url) {
        	log("Ajax: URL = "+url)
			result = $.ajax({
				method: method,
				url: url,
				data: data
			})
			.done( function(msg) {
				log("Ajax: done")
				settings.items = [];
				ajaxresult = msg;
				values = [];
				$.map(msg ,function(option) {
				    settings.items.push({'key': index(option, settings.key_path), 'value': index(option, settings.val_path)}) 
					values.push(index(option, settings.val_path))
				});
				log(msg[0])
				log("Key Path: "+key_path+" Value Path:"+val_path)
				log(settings.items[0])
				settings.done })
			.fail( function(msg) {
				log("Ajax: fail")
				log(msg)
				settings.fail })
			.always( settings.always );
        }


		  /*execute a function when someone writes in the text field:*/
		$(inputelement).on("input", function(e) {

			input = this

		    val = this.value;

		    closeAllLists(e, val);

		    //if (!val) { return false;}

		    currentFocus = -1;

		    a = document.createElement("DIV");
		    a.setAttribute("id", input.id + "autocomplete-list");
		    a.setAttribute("class", "autocomplete-items");
		    input.parentNode.appendChild(a);

		    arr = settings.items;
		    itemcount = 0
		    for (i = 0; i < arr.length; i++) {

		    	if (typeof arr[i] === 'string' || arr[i] instanceof String) {
		    		value = String(arr[i])
		    		key = String(arr[i])
		    	} else if (typeof arr[i] === 'array') {
		    		value = String(arr[i]['value'])
		    		key = String(arr[i]['key'])
		    	} else {
		    		value = String(arr[i].value)
		    		key = String(arr[i].key)
		    	}

		      if (value.substr(0, val.length).toUpperCase() == val.toUpperCase() && itemcount<settings.maxitems) {
		      	itemcount++
		        b = document.createElement("DIV");
		        b.innerHTML = "<strong>" + value.substr(0, val.length) + "</strong>";
		        b.innerHTML += value.substr(val.length);
		        b.innerHTML += "<input type='hidden' data-key='" + key + "' data-value='" + value + "'>";

		        b.addEventListener("click", function(e) {

					if (tagName=="SELECT") {
						selected_key = this.getElementsByTagName("input")[0].getAttribute("data-key")
						$(inputelementkey).val(selected_key)
					}
log("click")

					input.value = this.getElementsByTagName("input")[0].getAttribute("data-value");
					oldvalue = input.value

					closeAllLists(e, val);


					settings.click({'key': selected_key, 'value': input.value})

		        });

		        a.appendChild(b);

		      }
		    }
		  });

		  /*execute a function presses a key on the keyboard:*/
		  $(inputelement).on("keydown", function(e) {

		    var x = document.getElementById(this.id + "autocomplete-list");

		    if (x) x = x.getElementsByTagName("div");
		    if (e.keyCode == DOWN) {

		      currentFocus++;
		      addActive(x);

		    } else if (e.keyCode == UP) { 

		      currentFocus--;
		      addActive(x);

		    } else if (e.keyCode == ENTER) {

		      e.preventDefault();
		      if (currentFocus > -1) {
		        /*and simulate a click on the "active" item:*/
		        if (x) x[currentFocus].click();
		      }

		    }
		  });

		$(inputelement).blur( function(e) {		
			if (settings.inlist && !values.includes(this.value)) {
				$(this).val(oldvalue)
			}
			setTimeout(function(){
				//closeAllLists(this)
			}, 200);
		});

		$(inputelement).focus( function(e) {
			this.setSelectionRange(0, 9999)
		});

        //this._create(markup);
        return this

    };
    
    Bs4autocomplete.prototype = {        

        refresh: function(name, fn) {
            log("adding listener to event " + name);
            this._events[name] = fn;
            return this;
        },
        
        serialize: function() {
            var form = this.form.serialize();
            this.form.find('input[disabled][data-serialize="1"]').each(function() {
                form = form + '&' + $(this).attr('name') + '=' + $(this).val();
            });
            
            return form;
        },
        
        _defaultSubmit: function(wizard) {
            $.ajax({
                type: "POST",
                url: wizard.args.submitUrl,
                data: wizard.serialize(),
                dataType: "json"
            }).done(function(response) {
                wizard.submitSuccess();
                wizard.hideButtons();
                wizard.updateProgressBar(0);
            }).fail(function() {
                wizard.submitFailure();
                wizard.hideButtons();
            });
        }
    };

	var currentFocus;
	let UP = 38;
	let DOWN = 40;
	let ENTER = 13;


	function index(obj,is, value) {
	    if (!is)
	        return obj;
	    else if (typeof is == 'string')
	        return index(obj,is.split('.'), value);
	    else if (is.length==1 && value!==undefined)
	        return obj[is[0]] = value;
	    else if (is.length==0)
	        return obj;
	    else
	        return index(obj[is[0]],is.slice(1), value);
	}

	function addActive(x) {
		/*a function to classify an item as "active":*/
		if (!x) return false;
		/*start by removing the "active" class on all items:*/
		removeActive(x);
		if (currentFocus >= x.length) currentFocus = 0;
		if (currentFocus < 0) currentFocus = (x.length - 1);
		/*add class "autocomplete-active":*/
		x[currentFocus].classList.add("autocomplete-active");
	}

	function removeActive(x) {
		/*a function to remove the "active" class from all autocomplete items:*/
		for (var i = 0; i < x.length; i++) {
			x[i].classList.remove("autocomplete-active");
		}
	}

  	function closeAllLists(elmnt, inp) {


	    /*close all autocomplete lists in the document,
	    except the one passed as an argument:*/
	    var x = document.getElementsByClassName("autocomplete-items");
	    for (var i = 0; i < x.length; i++) {
			if (elmnt != x[i] && elmnt != inp) {
				x[i].parentNode.removeChild(x[i]);
			}
		}
	}

     function log(msg) {
        if (!window.console || !$.fn.bs4autocomplete.logging) {return;}
    	if (typeof msg === 'object' && msg !== null) {
    		console.log("bs4autocomplete: ");
    		console.log(msg)
    	} else {
    		console.log("bs4autocomplete: "+msg);
    	}
    }

    
}(window.jQuery));

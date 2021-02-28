
(function ($) {

	$.fn.bs4autocomplete = function(args) {
		return new Bs4autocomplete(this, args);
	};

	$.fn.bs4autocomplete.logging = false;

	Bs4autocomplete = function(element, options) {

		var bs4ac = this;

		this.settings = $.extend({
			version     : '0.1',
			items       : [],
			maxitems    : 10,
			inlist      : true,
			input_class : "form-control form-control-sm",
			div_class   : "autocomplete autocomplete-sm",
            // Ajax
            method      : "POST",
            baseurl     : "",
            module      : "",
            controller  : "",
            action      : "",
            data        : "",
            key_path    : "key",
            val_path    : "value",
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

		// settings = this.settings;

		var method     = element.data('method')     ? element.data('method')     : this.settings.method;
		var baseurl    = element.data('baseurl')    ? element.data('baseurl')    : this.settings.baseurl;
		var module     = element.data('module')     ? element.data('module')     : this.settings.module;
		var controller = element.data('controller') ? element.data('controller') : this.settings.controller;
		var action     = element.data('action')     ? element.data('action')     : this.settings.action;
		var data       = element.data('data')       ? element.data('data')       : this.settings.data;
		var url        = null
		if (baseurl) {
			var url    = baseurl+"/"+module+"/"+controller+"/"+action;
			var url    = url.replace(/^\/|\/$/g, '')  // replace("{id}", id).
		}
		var key_path   = element.data('key_path')   ? element.data('key_path')   : this.settings.key_path;
		var val_path   = element.data('val_path')   ? element.data('val_path')   : this.settings.val_path;

		var id         = $(element).attr("id")
		var tagName    = $(element).prop("tagName") // TEXT, SELECT
		var values     = this.settings.items
		var oldvalue   = $(element).val()
		var ajaxresult = []


		/* replace select input */
		if (tagName == "SELECT") { element = this.replaceSelect(element) }
var oldvalue   = $(element).val()

		this.log("Tag: "+tagName+" ID: #"+id+" oldvalue: "+oldvalue )

		/* get Select fom Ajax */
		if (url) {
			this.log("Ajax: URL = "+url)
			result = $.ajax({
				method: method,
				url: url,
				data: data
			})
			.done( function(msg) {
				this.log("Ajax: done")
				this.settings.items = [];
				ajaxresult = msg;
				values = [];
				$.map(msg ,function(option) {
					this.settings.items.push({'key': this.index(option, this.settings.key_path), 'value': this.index(option, this.settings.val_path)}) 
					values.push(this.index(option, this.settings.val_path))
				});
				this.log(msg[0])
				this.log("Key Path: "+key_path+" Value Path:"+val_path)
				this.log(this.settings.items[0])
				this.settings.done })
			.fail( function(msg) {
				this.log("Ajax: fail")
				this.log(msg)
				this.settings.fail })
			.always( this.settings.always );
		}

		/*execute a function when someone writes in the text field:*/
		$(element).on("input", function(e) {

			input = this
			val   = this.value;

			bs4ac.closeAllLists(e, val);

			currentFocus = -1;

			/* create list items */
			a = document.createElement("DIV");
			a.setAttribute("id", this.id + "autocomplete-list");
			a.setAttribute("class", "autocomplete-items");
			this.parentNode.appendChild(a);

			arr = bs4ac.settings.items;
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

				if (val.length>0 && value.toUpperCase().indexOf(val.toUpperCase()) >= 0 && itemcount<bs4ac.settings.maxitems) {
					itemcount++
					b = document.createElement("DIV");

					nStart = value.toUpperCase().indexOf(val.toUpperCase());
					b.innerHTML = value.substr(0, nStart);
					b.innerHTML += "<strong>" + value.substr(nStart, val.length) + "</strong>";
					b.innerHTML += value.substr(nStart+val.length);
					b.innerHTML += "<input type='hidden' data-key='" + key + "' data-value='" + value + "'>";

					b.addEventListener("click", function(e) {

						if (tagName=="SELECT") {
							selected_key = this.getElementsByTagName("input")[0].getAttribute("data-key")
					bs4ac.log("SelectedKey: "+selected_key)
					$(inputelementkey).val(selected_key)
				}

				input.value = this.getElementsByTagName("input")[0].getAttribute("data-value");
				oldvalue = input.value

				bs4ac.closeAllLists(e, val);

			});

					a.appendChild(b);

				}
			}
		});

		/*execute a function presses a key on the keyboard:*/
		$(element).on("keydown", function(e) {

			var x = document.getElementById(this.id + "autocomplete-list");

			if (x) x = x.getElementsByTagName("div");
			if (e.keyCode == DOWN) {

				currentFocus++;
				bs4ac.addActive(x);

			} else if (e.keyCode == UP) { 

				currentFocus--;
				bs4ac.addActive(x);

			} else if (e.keyCode == ENTER) {

				e.preventDefault();
				if (currentFocus > -1) {
					/*and simulate a click on the "active" item:*/
					if (x) x[currentFocus].click();
				}

			}
		});

		$(element).blur( function(e) {		
			if (bs4ac.settings.inlist && !values.includes(this.value)) {
				$(this).val(oldvalue)
			}	
			setTimeout(function(){
				//this.closeAllLists(this)
			}, 200);
		});

		$(element).focus( function(e) {
			this.setSelectionRange(0, 9999)
		});

        //this._create(markup);
        return this

    };
    
    Bs4autocomplete.prototype = {        

    	replaceSelect(element) {

    		let that = this
    		var select_name = $(element).attr("name")
    		var select_id = $(element).attr("id")

    		var options = $(element).find('option');
    		var selected = $(element).find('option:selected');
    		var oldkey = $(selected).val()
    		var oldvalue = $(selected).text()

    		if (this.settings.items.length==0) {
    			values = []
    			var _values = $.map(options ,function(option) {
    				that.settings.items.push({'key': option.value, 'value': option.text}) 
    				values.push(option.text)
    			});
    		}
			// add new input fields
			new_input_key = '<input type="hidden" id="'+select_id+'_key" value="'+oldkey+'" name="'+select_name+'" placeholder="key" >'
			new_input_value  = '<input autocomplete="off" type="search" id="'+select_id+'" value="'+oldvalue+'" class="'+this.settings.input_class+'">'
			new_div = '<div class="'+this.settings.div_class+'">'+new_input_key+new_input_value+'</div>'
			$(element).before(new_div)

			// remove old select input
			$(element).remove()

			inputelementkey = $("#"+select_id+"_key")
			element = $("#"+select_id)

			return element;
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
		},

		index(obj,is, value) {
			if (!is)
				return obj;
			else if (typeof is == 'string')
				return this.index(obj,is.split('.'), value);
			else if (is.length==1 && value!==undefined)
				return obj[is[0]] = value;
			else if (is.length==0)
				return obj;
			else
				return this.index(obj[is[0]],is.slice(1), value);
		},

		addActive(x) {
			/*a function to classify an item as "active":*/
			if (!x) return false;
			/*start by removing the "active" class on all items:*/
			this.removeActive(x);
			if (currentFocus >= x.length) currentFocus = 0;
			if (currentFocus < 0) currentFocus = (x.length - 1);
			/*add class "autocomplete-active":*/
			x[currentFocus].classList.add("autocomplete-active");
		},

		removeActive(x) {
			/*a function to remove the "active" class from all autocomplete items:*/
			for (var i = 0; i < x.length; i++) {
				x[i].classList.remove("autocomplete-active");
			}
		},

		closeAllLists: function(elmnt, inp) {


		    /*close all autocomplete lists in the document,
		    except the one passed as an argument:*/
		    var x = document.getElementsByClassName("autocomplete-items");
		    for (var i = 0; i < x.length; i++) {
		    	if (elmnt != x[i] && elmnt != inp) {
		    		x[i].parentNode.removeChild(x[i]);
		    	}
		    }
		},

		log(msg) {
			if (!window.console || !$.fn.bs4autocomplete.logging) {return;}
			if (typeof msg === 'object' && msg !== null) {
				console.log("bs4autocomplete: ");
				console.log(msg)
			} else {
				console.log("bs4autocomplete: "+msg);
			}
		}

	};

	var currentFocus;
	let UP = 38;
	let DOWN = 40;
	let ENTER = 13;


}(window.jQuery));


/*

		if (tagName == "SEL_ECT") {

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

		*/

function removeLastElement(element) {
	var lastElement = element.lastChild;
	remove(lastElement);
}



function remove(element) {
	var parent = element.parentNode;
	if(parent) {
		parent.removeChild(element);
	}
}

function removeElements(element) {
	while(element.hasChildNodes()) {     
		element.removeChild(element.childNodes[0]);
	}
}

function escapeHTML(str) {
    return encodeURIComponent(str.replace(/[&<>"']/g, match => {
        const escapeMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return escapeMap[match];
    }));
}

function unescapeHTML(escapedStr) {
    return decodeURIComponent(escapedStr).replace(/&(amp|lt|gt|quot|#39);/g, match => {
        const unescapeMap = {
            '&amp;': '&',
            '&lt;': '<',
            '&gt;': '>',
            '&quot;': '"',
            '&#39;': "'"
        };
        return unescapeMap[match];
    });
}




function objectSize(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
}



function getMousePosition(e) {
	var xPosition = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft; 
	var yPosition = e.clientY + document.body.scrollTop + document.documentElement.scrollTop; 

    return { x: xPosition, y: yPosition };
}


function titleCase(str) {
  return str.toLowerCase().split(' ').map(function(word) {
    return (word.charAt(0).toUpperCase() + word.slice(1));
  }).join(' ');
}



function d2h(d) {
    return d.toString(16);
}


function h2d (h) {
    return parseInt(h, 16);
}


function stringToHex (tmp) {
    var str = '',
        i = 0,
        tmp_len = tmp.length,
        c;
 
    for (; i < tmp_len; i += 1) {
        c = tmp.charCodeAt(i);
        str += d2h(c) + '';
    }
    return str;
}


function hexToString (tmp) {
    var arr = chunkString(tmp,2),
        str = '',
        i = 0,
        arr_len = arr.length,
        c;
 
    for (; i < arr_len; i += 1) {
        c = String.fromCharCode( h2d( arr[i] ) );
        str += c;
    }
 
    return str;

}

function chunkString(str, len) {
  var _size = Math.ceil(str.length/len),
      _ret  = new Array(_size),
      _offset
  ;

  for (var _i=0; _i<_size; _i++) {
    _offset = _i * len;
    _ret[_i] = str.substring(_offset, _offset + len);
  }

  return _ret;
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
};

// UNSAFE with unsafe strings; only use on previously-escaped ones!
function unescapeHtml(escapedStr) {
    var div = document.createElement('div');
    div.innerHTML = escapedStr;
    var child = div.childNodes[0];
    return child ? child.nodeValue : '';
};



function showAlert(message, duration = 10000) {
    // Create container
    const alertContainer = document.createElement('div');
    alertContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        background-color: white;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        padding: 16px 40px 16px 16px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;

    // Add the message
    const messageText = document.createElement('p');
    messageText.style.cssText = `
        margin: 0;
        color: #1a202c;
        font-family: system-ui, -apple-system, sans-serif;
        font-size: 14px;
        line-height: 1.5;
    `;
    messageText.textContent = message;
    alertContainer.appendChild(messageText);

    // Add close button
    const closeButton = document.createElement('button');
    closeButton.innerHTML = '×';
    closeButton.style.cssText = `
        position: absolute;
        top: 12px;
        right: 12px;
        border: none;
        background: none;
        font-size: 20px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    `;

    closeButton.addEventListener('mouseover', () => {
        closeButton.style.backgroundColor = '#f0f0f0';
    });

    closeButton.addEventListener('mouseout', () => {
        closeButton.style.backgroundColor = 'transparent';
    });

    alertContainer.appendChild(closeButton);

    // Add animation keyframes
    const styleSheet = document.createElement('style');
    styleSheet.textContent = `
        @keyframes slideIn {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(styleSheet);

    // Function to remove the alert
    const removeAlert = () => {
        alertContainer.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            if (alertContainer.parentNode) {
                document.body.removeChild(alertContainer);
            }
        }, 300);
    };

    // Add click handler to close button
    closeButton.addEventListener('click', removeAlert);

    // Add to document
    document.body.appendChild(alertContainer);

    // Set auto-dismiss timer
    setTimeout(removeAlert, duration);

    // Return a function that can be used to manually close the alert
    return removeAlert;
}

// Usage example:
// const close = showAlert("Your message here", 10000);
// To close manually: close();

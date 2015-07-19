var FailWhale;
(function (FailWhale) {
    function rescroll(document) {
        function view() {
            return {
                x: document.documentElement.scrollLeft || document.body.scrollLeft,
                y: document.documentElement.scrollTop || document.body.scrollTop,
                w: document.documentElement.clientWidth || window.innerWidth,
                h: document.documentElement.clientHeight || window.innerHeight
            };
        }
        function doc() {
            return {
                w: document.documentElement.scrollWidth || document.body.scrollWidth,
                h: document.documentElement.scrollHeight || document.body.scrollHeight
            };
        }
        var doc1 = doc();
        var view1 = view();
        return function () {
            var doc2 = doc();
            var view2 = view();
            window.scrollTo(view1.x + view1.w >= doc1.w && view1.x != 0 ? doc2.w - view2.w : view2.x, view1.y + view1.h >= doc1.h && view1.y != 0 ? doc2.h - view2.h : view2.y);
        };
    }
    var Data;
    (function (Data) {
        Data.Type = {
            STRING: 'string',
            ARRAY: 'array',
            OBJECT: 'object',
            INT: 'int',
            TRUE: 'true',
            FALSE: 'false',
            NULL: 'null',
            POS_INF: '+inf',
            NEG_INF: '-inf',
            NAN: 'nan',
            UNKNOWN: 'unknown',
            FLOAT: 'float',
            RESOURCE: 'resource',
            EXCEPTION: 'exception'
        };
        function visit(x, f) {
            switch (x.type) {
                case Data.Type.INT:
                    return f.visitInt(x.int);
                case Data.Type.FLOAT:
                    return f.visitFloat(x.float);
                case Data.Type.TRUE:
                    return f.visitTrue();
                case Data.Type.FALSE:
                    return f.visitFalse();
                case Data.Type.STRING:
                    return f.visitString(x.string);
                case Data.Type.POS_INF:
                    return f.visitPosInf();
                case Data.Type.NEG_INF:
                    return f.visitNegInf();
                case Data.Type.NAN:
                    return f.visitNaN();
                case Data.Type.ARRAY:
                    return f.visitArray(x.array);
                case Data.Type.OBJECT:
                    return f.visitObject(x.object);
                case Data.Type.EXCEPTION:
                    return f.visitException(x.exception);
                case Data.Type.RESOURCE:
                    return f.visitResource(x.resource);
                case Data.Type.NULL:
                    return f.visitNull();
                case Data.Type.UNKNOWN:
                    return f.visitUnknown();
                default:
                    throw "unknown type " + x.type;
            }
        }
        Data.visit = visit;
    })(Data || (Data = {}));
    var Renderer = (function () {
        function Renderer(root, document) {
            this.padding = '3px';
            this.root = root;
            this.document = document;
        }
        Renderer.prototype.plain = function (content, inline) {
            if (inline === void 0) { inline = true; }
            var span = this.document.createElement(inline ? 'span' : 'div');
            span.appendChild(this.document.createTextNode(content));
            return span;
        };
        Renderer.prototype.italics = function (t) {
            var wrapped = this.plain(t);
            wrapped.style.display = 'inline';
            wrapped.style.fontStyle = 'italic';
            return wrapped;
        };
        Renderer.prototype.notice = function (t) {
            var wrapped = this.plain(t);
            wrapped.style.fontStyle = 'italic';
            wrapped.style.padding = this.padding;
            wrapped.style.display = 'inline-block';
            return wrapped;
        };
        Renderer.prototype.collect = function (nodes) {
            var x = this.document.createDocumentFragment();
            for (var i = 0; i < nodes.length; i++)
                x.appendChild(nodes[i]);
            return x;
        };
        Renderer.prototype.expandable = function (content) {
            var _this = this;
            var container = this.document.createElement('div');
            var inline = content.inline;
            if (inline === undefined)
                inline = true;
            if (inline)
                container.style.display = 'inline-table';
            var head = this.document.createElement('div');
            head.style.backgroundColor = '#eee';
            head.style.cursor = 'pointer';
            head.style.padding = this.padding;
            head.addEventListener('mouseenter', function () {
                head.style.backgroundColor = '#ddd';
                body.style.borderColor = '#ddd';
            });
            head.addEventListener('mouseleave', function () {
                head.style.backgroundColor = '#eee';
                body.style.borderColor = '#eee';
            });
            head.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
            head.appendChild(content.head);
            container.appendChild(head);
            var body = this.document.createElement('div');
            body.style.borderSpacing = '0';
            body.style.padding = '0';
            body.style.backgroundColor = 'white';
            body.style.borderColor = '#eee';
            body.style.borderWidth = '1px';
            body.style.borderTopWidth = '0';
            body.style.borderStyle = 'solid';
            container.appendChild(body);
            var open = content.open;
            var refresh = function () {
                body.innerHTML = '';
                if (open) {
                    body.appendChild(content.body());
                }
                body.style.display = open ? 'block' : 'none';
            };
            refresh();
            head.addEventListener('click', function () {
                var scroll = rescroll(_this.document);
                open = !open;
                refresh();
                scroll();
            });
            return container;
        };
        Renderer.prototype.table = function (data) {
            var table = this.document.createElement('table');
            table.style.borderSpacing = '0';
            table.style.padding = '0';
            for (var i = 0; i < data.length; i++) {
                var tr = this.document.createElement('tr');
                table.appendChild(tr);
                for (var j = 0; j < data[i].length; j++) {
                    var td = this.document.createElement('td');
                    td.style.padding = this.padding;
                    td.style.verticalAlign = 'baseline';
                    td.appendChild(data[i][j]);
                    tr.appendChild(td);
                }
            }
            return table;
        };
        Renderer.prototype.bold = function (content) {
            var box = this.plain(content);
            box.style.fontWeight = 'bold';
            return box;
        };
        Renderer.prototype.keyword = function (word) {
            var box = this.plain(word);
            box.style.color = '#009';
            box.style.fontWeight = 'bold';
            return box;
        };
        Renderer.prototype.renderRoot = function () {
            var container = this.document.createElement('div');
            container.style.whiteSpace = 'pre';
            container.style.fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
            container.style.fontSize = "10pt";
            container.style.lineHeight = '16px';
            container.appendChild(this.renderValue(this.root.root));
            return container;
        };
        Renderer.prototype.renderValue = function (value) {
            return Data.visit(value, this);
        };
        Renderer.prototype.visitInt = function (val) {
            return this.renderNumber(String(val));
        };
        Renderer.prototype.visitFloat = function (val) {
            var str = String(val);
            str = val % 1 == 0 ? str + '.0' : str;
            return this.renderNumber(str);
        };
        Renderer.prototype.visitTrue = function () {
            return this.keyword('true');
        };
        Renderer.prototype.visitFalse = function () {
            return this.keyword('false');
        };
        Renderer.prototype.visitString = function (id) {
            return this.renderString(this.root.strings[id]);
        };
        Renderer.prototype.visitPosInf = function () {
            return this.renderNumber('INF');
        };
        Renderer.prototype.visitNegInf = function () {
            return this.renderNumber('-INF');
        };
        Renderer.prototype.visitNaN = function () {
            return this.renderNumber('NAN');
        };
        Renderer.prototype.visitArray = function (id) {
            return this.renderArray(id);
        };
        Renderer.prototype.visitObject = function (id) {
            return this.renderObject(this.root.objects[id]);
        };
        Renderer.prototype.visitException = function (val) {
            return this.renderException(val);
        };
        Renderer.prototype.visitResource = function (val) {
            return this.collect([this.keyword('resource'), this.plain(' ' + val.type)]);
        };
        Renderer.prototype.visitNull = function () {
            return this.keyword('null');
        };
        Renderer.prototype.visitUnknown = function () {
            var span = this.plain('unknown type');
            span.style.fontStyle = 'italic';
            return span;
        };
        Renderer.prototype.renderArray = function (id) {
            var _this = this;
            var array = this.root.arrays[id];
            return this.expandable({
                head: this.keyword('array'),
                body: function () {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return _this.notice('empty');
                    var container = _this.document.createDocumentFragment();
                    container.appendChild(_this.table(array.entries.map(function (x) {
                        return [
                            _this.renderValue(x.key),
                            _this.plain('=>'),
                            _this.renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(_this.notice(array.entriesMissing + " entries missing..."));
                    return container;
                },
                open: false
            });
        };
        Renderer.prototype.renderObject = function (object) {
            var _this = this;
            return this.expandable({
                head: this.collect([this.keyword('new'), this.plain(' ' + object.className)]),
                body: function () {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return _this.notice('empty');
                    var container = _this.document.createDocumentFragment();
                    container.appendChild(_this.table(object.properties.map(function (property) {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';
                        return [
                            _this.collect([
                                _this.keyword(property.access),
                                _this.plain(' ' + prefix),
                                _this.renderVariable(property.name)
                            ]),
                            _this.plain('='),
                            _this.renderValue(property.value)
                        ];
                    })));
                    if (object.propertiesMissing > 0)
                        container.appendChild(_this.notice(object.propertiesMissing + " properties missing..."));
                    return container;
                },
                open: false
            });
        };
        Renderer.prototype.renderStack = function (stack, missing) {
            var _this = this;
            var renderFunctionCall = function (call) {
                var result = _this.document.createDocumentFragment();
                var prefix = '';
                if (call.object) {
                    var object = _this.root.objects[call.object];
                    result.appendChild(_this.renderObject(object));
                    prefix += '->';
                    if (object.className !== call.className)
                        prefix += call.className + '::';
                }
                else if (call.className) {
                    prefix += call.className;
                    prefix += call.isStatic ? '::' : '->';
                }
                result.appendChild(_this.plain(prefix + call.functionName));
                if (call.args instanceof Array) {
                    if (call.args.length == 0 && call.argsMissing == 0) {
                        result.appendChild(_this.plain('()'));
                    }
                    else {
                        result.appendChild(_this.plain('( '));
                        for (var i = 0; i < call.args.length; i++) {
                            if (i != 0)
                                result.appendChild(_this.plain(', '));
                            var arg = call.args[i];
                            if (arg.name) {
                                if (arg.typeHint) {
                                    var typeHint;
                                    switch (arg.typeHint) {
                                        case 'array':
                                        case 'callable':
                                            typeHint = _this.keyword(arg.typeHint);
                                            break;
                                        default:
                                            typeHint = _this.plain(arg.typeHint);
                                    }
                                    result.appendChild(typeHint);
                                    result.appendChild(_this.plain(' '));
                                }
                                if (arg.isReference) {
                                    result.appendChild(_this.plain('&'));
                                }
                                result.appendChild(_this.renderVariable(arg.name));
                                result.appendChild(_this.plain(' = '));
                            }
                            result.appendChild(_this.renderValue(arg.value));
                        }
                        if (call.argsMissing > 0) {
                            if (i != 0)
                                result.appendChild(_this.plain(', '));
                            result.appendChild(_this.italics(call.argsMissing + ' arguments missing...'));
                        }
                        result.appendChild(_this.plain(' )'));
                    }
                }
                else {
                    result.appendChild(_this.plain('( '));
                    result.appendChild(_this.italics('not available'));
                    result.appendChild(_this.plain(' )'));
                }
                return result;
            };
            var rows = [];
            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    this.plain('#' + String(x + 1)),
                    this.renderLocation(stack[x].location),
                    renderFunctionCall(stack[x])
                ]);
            }
            if (missing == 0) {
                rows.push([
                    this.plain('#' + String(x + 1)),
                    this.expandable({
                        head: this.plain('{main}'),
                        body: function () {
                            return _this.notice('no source code');
                        },
                        open: false
                    }),
                    this.collect([])
                ]);
            }
            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(rows));
            if (missing > 0)
                container.appendChild(this.notice(missing + " stack frames missing..."));
            return container;
        };
        Renderer.prototype.renderVariable = function (name) {
            var _this = this;
            var red = function (v) {
                var result = _this.plain(v);
                result.style.color = '#700';
                return result;
            };
            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return this.collect([red('$' + '{'), this.renderString({ bytes: name, bytesMissing: 0 }), red('}')]);
        };
        Renderer.prototype.renderLocals = function (locals, missing) {
            var _this = this;
            if (!(locals instanceof Array))
                return this.notice('not available');
            if (locals.length == 0 && missing == 0)
                return this.notice('none');
            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(locals.map(function (local) {
                return [
                    _this.renderVariable(local.name),
                    _this.plain('='),
                    _this.renderValue(local.value)
                ];
            })));
            if (missing > 0)
                container.appendChild(this.notice(missing + " variables missing..."));
            return container;
        };
        Renderer.prototype.renderGlobals = function (globals) {
            var _this = this;
            if (!globals)
                return this.notice('not available');
            var staticVariables = globals.staticVariables;
            var staticProperties = globals.staticProperties;
            var globalVariables = globals.globalVariables;
            var rows = [];
            for (var i = 0; i < globalVariables.length; i++) {
                var pieces = this.document.createDocumentFragment();
                var v2 = globalVariables[i];
                var superGlobals = [
                    'GLOBALS',
                    '_SERVER',
                    '_GET',
                    '_POST',
                    '_FILES',
                    '_COOKIE',
                    '_SESSION',
                    '_REQUEST',
                    '_ENV'
                ];
                if (superGlobals.indexOf(v2.name) == -1) {
                    pieces.appendChild(this.keyword('global'));
                    pieces.appendChild(this.plain(' '));
                }
                pieces.appendChild(this.renderVariable(v2.name));
                rows.push([pieces, this.plain('='), this.renderValue(v2.value)]);
            }
            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = this.document.createDocumentFragment();
                pieces.appendChild(this.keyword(p.access));
                pieces.appendChild(this.plain(' '));
                pieces.appendChild(this.plain(p.className + '::'));
                pieces.appendChild(this.renderVariable(p.name));
                rows.push([pieces, this.plain('='), this.renderValue(p.value)]);
            }
            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = this.document.createDocumentFragment();
                pieces.appendChild(this.keyword('function'));
                pieces.appendChild(this.plain(' '));
                if (v.className)
                    pieces.appendChild(this.plain(v.className + '::'));
                pieces.appendChild(this.plain(v.functionName + '()::'));
                pieces.appendChild(this.renderVariable(v.name));
                rows.push([
                    pieces,
                    this.plain('='),
                    this.renderValue(v.value)
                ]);
            }
            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(rows));
            var block = function (node) {
                var div = _this.document.createElement('div');
                div.appendChild(node);
                return div;
            };
            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(this.notice(globals.staticPropertiesMissing + " static properties missing...")));
            if (globals.globalVariablesMissing > 0)
                container.appendChild(block(this.notice(globals.globalVariablesMissing + " global variables missing...")));
            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(this.notice(globals.staticPropertiesMissing + " static variables missing...")));
            return container;
        };
        Renderer.prototype.renderException = function (x) {
            var _this = this;
            if (!x)
                return this.italics('none');
            return this.expandable({
                head: this.collect([this.keyword('exception'), this.plain(' ' + x.className)]),
                body: function () {
                    var body = _this.document.createElement('div');
                    body.appendChild(_this.expandable({
                        inline: false,
                        open: true,
                        head: _this.bold('exception'),
                        body: function () {
                            return _this.table([
                                [_this.bold('code'), _this.plain(x.code)],
                                [_this.bold('message'), _this.plain(x.message)],
                                [_this.bold('location'), _this.renderLocation(x.location, true)],
                                [_this.bold('previous'), _this.renderException(x.previous)]
                            ]);
                        }
                    }));
                    body.appendChild(_this.expandable({
                        inline: false,
                        open: true,
                        head: _this.bold('locals'),
                        body: function () {
                            return _this.renderLocals(x.locals, x.localsMissing);
                        }
                    }));
                    body.appendChild(_this.expandable({
                        inline: false,
                        open: true,
                        head: _this.bold('stack'),
                        body: function () {
                            return _this.renderStack(x.stack, x.stackMissing);
                        }
                    }));
                    body.appendChild(_this.expandable({
                        inline: false,
                        open: true,
                        head: _this.bold('globals'),
                        body: function () {
                            return _this.renderGlobals(x.globals);
                        }
                    }));
                    body.style.padding = _this.padding;
                    return body;
                },
                open: true
            });
        };
        Renderer.prototype.renderLocation = function (location, open) {
            var _this = this;
            if (open === void 0) { open = false; }
            return this.expandable({
                head: location
                    ? this.collect([this.plain(location.file + ':'), this.renderNumber(String(location.line))])
                    : this.plain('[internal function]'),
                body: function () {
                    if (!location || !location.source)
                        return _this.notice('no source code');
                    var padding = '4px';
                    var lineNumber = _this.document.createElement('div');
                    lineNumber.style.display = 'inline-block';
                    lineNumber.style.padding = padding;
                    lineNumber.style.textAlign = 'right';
                    lineNumber.style.color = '#999';
                    lineNumber.style.backgroundColor = '#333';
                    lineNumber.style.borderRightColor = '#666';
                    lineNumber.style.borderRightWidth = '1px';
                    lineNumber.style.borderRightStyle = 'dashed';
                    lineNumber.style.verticalAlign = 'top';
                    lineNumber.style.minWidth = '32px';
                    var code = _this.document.createElement('div');
                    code.style.display = 'inline-block';
                    code.style.padding = padding;
                    code.style.width = '800px';
                    code.style.overflowX = 'auto';
                    code.style.backgroundColor = '#222';
                    code.style.color = '#ccc';
                    code.style.verticalAlign = 'top';
                    var codeDiv = _this.document.createElement('div');
                    code.appendChild(codeDiv);
                    codeDiv.style.display = 'inline-block';
                    codeDiv.style.minWidth = '100%';
                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;
                        var lineDiv = _this.plain(decodeUTF8(location.source[codeLine]) + "\n", false);
                        if (codeLine == location.line) {
                            lineDiv.style.backgroundColor = '#f88';
                            lineDiv.style.color = '#300';
                            lineDiv.style.borderRadius = padding;
                        }
                        lineNumber.appendChild(_this.plain(String(codeLine) + "\n", false));
                        codeDiv.appendChild(lineDiv);
                    }
                    return _this.collect([lineNumber, code]);
                },
                open: open
            });
        };
        Renderer.prototype.renderString = function (x) {
            var _this = this;
            var doRender = function () {
                var span = _this.document.createElement('span');
                span.style.color = '#080';
                span.style.fontWeight = 'bold';
                var translate = {
                    '\\': '\\\\',
                    '$': '\\$',
                    '\r': '\\r',
                    '\v': '\\v',
                    '\f': '\\f',
                    '"': '\\"'
                };
                var buffer = '"';
                for (var i = 0; i < x.bytes.length; i++) {
                    var char = x.bytes.charAt(i);
                    var code = x.bytes.charCodeAt(i);
                    if (translate[char] !== undefined) {
                        var escaped = translate[char];
                    }
                    else if ((code < 32 || code > 126) && char !== '\n' && char != '\t') {
                        escaped = '\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16));
                    }
                    else {
                        escaped = undefined;
                    }
                    if (escaped !== undefined) {
                        if (buffer.length > 0)
                            span.appendChild(_this.plain(buffer));
                        buffer = "";
                        span.appendChild(_this.keyword(escaped));
                    }
                    else {
                        buffer += char;
                    }
                }
                span.appendChild(_this.plain(buffer + '"'));
                var container = _this.document.createElement('div');
                container.style.display = 'inline-table';
                container.appendChild(span);
                if (x.bytesMissing > 0) {
                    container.appendChild(_this.plain(' '));
                    container.appendChild(_this.italics(x.bytesMissing + ' bytes missing...'));
                }
                return container;
            };
            var visualLength = 0;
            for (var i = 0; i < x.bytes.length; i++) {
                var code = x.bytes.charCodeAt(i);
                var isPrintable = code >= 32 && code <= 126;
                visualLength += isPrintable ? 1 : 4;
            }
            var numLines = x.bytes.split("\n").length;
            if (visualLength > 200 || numLines > 20)
                return this.expandable({ open: false, head: this.keyword('string'), body: doRender });
            else
                return doRender();
        };
        Renderer.prototype.renderNumber = function (x) {
            var result = this.plain(x);
            result.style.color = '#00f';
            return result;
        };
        return Renderer;
    })();
    function renderJSON(json, document) {
        var root = JSON.parse(json);
        return new Renderer(root, document).renderRoot();
    }
    FailWhale.renderJSON = renderJSON;
    function decodeUTF8(utf8Bytes) {
        return decodeURIComponent(escape(utf8Bytes));
    }
})(FailWhale || (FailWhale = {}));
//# sourceMappingURL=script.js.map
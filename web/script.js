var FailWhale;
(function (FailWhale) {
    function rescroll() {
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
    })(Data || (Data = {}));

    var Settings;
    (function (Settings) {
        Settings.fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
        Settings.fontSize = '10pt';
        Settings.padding = '0.25em';
        Settings.borderWidth = '0.125em';
    })(Settings || (Settings = {}));

    var HTML;
    (function (HTML) {
        function inline(inner) {
            var s = document.createElement('div');
            s.style.display = 'inline-block';
            s.appendChild(inner);
            return s;
        }
        HTML.inline = inline;

        function plain(content) {
            return document.createTextNode(content);
        }
        HTML.plain = plain;

        function italics(t) {
            var wrapped = document.createElement('span');
            wrapped.appendChild(HTML.plain(t));
            wrapped.style.display = 'inline';
            wrapped.style.fontStyle = 'italic';
            return wrapped;
        }
        HTML.italics = italics;

        function notice(t) {
            var wrapped = document.createElement('span');
            wrapped.appendChild(HTML.plain(t));
            wrapped.style.fontStyle = 'italic';
            wrapped.style.padding = Settings.padding;
            wrapped.style.display = 'inline-block';
            return wrapped;
        }
        HTML.notice = notice;

        function collect(nodes) {
            var x = document.createDocumentFragment();
            for (var i = 0; i < nodes.length; i++)
                x.appendChild(nodes[i]);
            return x;
        }
        HTML.collect = collect;

        function expandable(content) {
            var container = document.createElement('div');

            var head = document.createElement('div');
            head.style.backgroundColor = '#eee';
            head.style.cursor = 'pointer';
            head.style.padding = Settings.padding;
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

            var body = document.createElement('table');
            body.style.borderSpacing = '0';
            body.style.padding = '0';
            body.style.backgroundColor = 'white';
            body.style.borderColor = '#eee';
            body.style.borderWidth = Settings.borderWidth;
            body.style.borderTopWidth = '0px';
            body.style.borderStyle = 'solid';
            body.style.width = '100%';
            container.appendChild(body);

            var open = content.open;

            function refresh() {
                body.innerHTML = '';

                if (open) {
                    var td = document.createElement('td');
                    var tr = document.createElement('tr');
                    td.style.padding = '0';
                    td.appendChild(content.body());
                    tr.appendChild(td);
                    body.appendChild(tr);
                }

                body.style.display = open ? 'table' : 'none';
            }

            refresh();

            head.addEventListener('click', function () {
                var scroll = rescroll();
                open = !open;
                refresh();
                scroll();
            });

            return container;
        }
        HTML.expandable = expandable;

        function table(data) {
            var table = document.createElement('table');
            table.style.borderSpacing = '0';
            table.style.padding = '0';

            for (var i = 0; i < data.length; i++) {
                var tr = document.createElement('tr');
                table.appendChild(tr);
                for (var j = 0; j < data[i].length; j++) {
                    var td = document.createElement('td');
                    td.style.padding = Settings.padding;
                    td.style.verticalAlign = 'baseline';
                    td.appendChild(data[i][j]);
                    tr.appendChild(td);
                }
            }

            return table;
        }
        HTML.table = table;

        function bold(content) {
            var box = document.createElement('span');
            box.appendChild(HTML.plain(content));
            box.style.fontWeight = 'bold';
            return box;
        }
        HTML.bold = bold;

        function keyword(word) {
            var box = document.createElement('span');
            box.appendChild(HTML.plain(word));
            box.style.color = '#008';
            box.style.fontWeight = 'bold';
            return box;
        }
        HTML.keyword = keyword;
    })(HTML || (HTML = {}));

    function renderJSON(json) {
        var root = JSON.parse(json);

        var container = document.createElement('div');
        container.style.whiteSpace = 'pre';
        container.style.fontFamily = Settings.fontFamily;
        container.style.fontSize = Settings.fontSize;
        container.appendChild(renderValue(root.root));
        return container;

        function renderValue(x) {
            switch (x.type) {
                case Data.Type.INT:
                    return renderNumber(String(x.int));
                case Data.Type.FLOAT:
                    var str = String(x.float);
                    str = x.float % 1 == 0 ? str + '.0' : str;
                    return renderNumber(str);
                case Data.Type.TRUE:
                    return HTML.keyword('true');
                case Data.Type.FALSE:
                    return HTML.keyword('false');
                case Data.Type.STRING:
                    return renderString(root.strings[x.string]);
                case Data.Type.POS_INF:
                    return renderNumber('INF');
                case Data.Type.NEG_INF:
                    return HTML.collect([HTML.plain('-'), renderNumber('INF')]);
                case Data.Type.NAN:
                    return renderNumber('NAN');
                case Data.Type.ARRAY:
                    return renderArray(x.array);
                case Data.Type.OBJECT:
                    return renderObject(root.objects[x.object]);
                case Data.Type.EXCEPTION:
                    return renderException(x.exception);
                case Data.Type.RESOURCE:
                    return HTML.collect([HTML.keyword('resource'), HTML.plain(' ' + x.resource.type)]);
                case Data.Type.NULL:
                    return HTML.keyword('null');
                case Data.Type.UNKNOWN:
                    return HTML.keyword('unknown type');
                default:
                    throw "unknown type " + x.type;
            }
        }

        function renderArray(id) {
            var array = root.arrays[id];
            return HTML.inline(HTML.expandable({
                head: HTML.keyword('array'),
                body: function () {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return HTML.notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(HTML.table(array.entries.map(function (x) {
                        return [
                            renderValue(x.key),
                            HTML.plain('=>'),
                            renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(HTML.notice(array.entriesMissing + " entries missing..."));

                    return container;
                },
                open: false
            }));
        }

        function renderObject(object) {
            return HTML.inline(HTML.expandable({
                head: HTML.collect([HTML.keyword('new'), HTML.plain(' ' + object.className)]),
                body: function () {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return HTML.notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(HTML.table(object.properties.map(function (property) {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';

                        return [
                            HTML.collect([
                                HTML.keyword(property.access),
                                HTML.plain(' ' + prefix),
                                renderVariable(property.name)
                            ]),
                            HTML.plain('='),
                            renderValue(property.value)
                        ];
                    })));

                    if (object.propertiesMissing > 0)
                        container.appendChild(HTML.notice(object.propertiesMissing + " properties missing..."));

                    return container;
                },
                open: false
            }));
        }

        function renderStack(stack, missing) {
            function renderFunctionCall(call) {
                var result = document.createDocumentFragment();
                var prefix = '';
                if (call.object) {
                    var object = root.objects[call.object];
                    result.appendChild(renderObject(object));
                    prefix += '->';
                    if (object.className !== call.className)
                        prefix += call.className + '::';
                } else if (call.className) {
                    prefix += call.className;
                    prefix += call.isStatic ? '::' : '->';
                }

                result.appendChild(HTML.plain(prefix + call.functionName + '('));

                for (var i = 0; i < call.args.length; i++) {
                    if (i != 0)
                        result.appendChild(HTML.plain(', '));

                    result.appendChild(renderValue(call.args[i]));
                }

                if (call.argsMissing > 0) {
                    result.appendChild(HTML.plain(', '));
                    result.appendChild(HTML.italics(call.argsMissing + ' arguments missing...'));
                }

                result.appendChild(HTML.plain(')'));

                return result;
            }

            var rows = [];

            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    HTML.plain('#' + String(x + 1)),
                    renderLocation(stack[x].location),
                    renderFunctionCall(stack[x])
                ]);
            }

            if (missing == 0) {
                rows.push([
                    HTML.plain('#' + String(x + 1)),
                    HTML.inline(HTML.expandable({
                        head: HTML.plain('{main}'),
                        body: function () {
                            return HTML.notice('no source code');
                        },
                        open: false
                    })),
                    HTML.collect([])
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(HTML.table(rows));
            if (missing > 0)
                container.appendChild(HTML.notice(missing + " stack frames missing..."));

            return container;
        }

        function renderVariable(name) {
            function red(v) {
                var result = document.createElement('span');
                result.appendChild(HTML.plain(v));
                result.style.color = '#600';
                return result;
            }

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return HTML.collect([red('$' + '{'), renderString({ bytes: name, bytesMissing: 0 }), red('}')]);
        }

        function renderLocals(locals, missing) {
            if (!(locals instanceof Array))
                return HTML.notice('not available');

            if (locals.length == 0 && missing == 0)
                return HTML.notice('none');

            var container = document.createDocumentFragment();
            container.appendChild(HTML.table(locals.map(function (local) {
                return [
                    renderVariable(local.name),
                    HTML.plain('='),
                    renderValue(local.value)
                ];
            })));

            if (missing > 0)
                container.appendChild(HTML.notice(missing + " variables missing..."));

            return container;
        }

        function renderGlobals(globals) {
            if (!globals)
                return HTML.notice('not available');

            var staticVariables = globals.staticVariables;
            var staticProperties = globals.staticProperties;
            var globalVariables = globals.globalVariables;
            var rows = [];

            for (var i = 0; i < globalVariables.length; i++) {
                var pieces = document.createDocumentFragment();
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
                    pieces.appendChild(HTML.keyword('global'));
                    pieces.appendChild(HTML.plain(' '));
                }
                pieces.appendChild(renderVariable(v2.name));

                rows.push([pieces, HTML.plain('='), renderValue(v2.value)]);
            }

            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(HTML.keyword(p.access));
                pieces.appendChild(HTML.plain(' '));
                pieces.appendChild(HTML.plain(p.className + '::'));
                pieces.appendChild(renderVariable(p.name));

                rows.push([pieces, HTML.plain('='), renderValue(p.value)]);
            }

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(HTML.keyword('function'));
                pieces.appendChild(HTML.plain(' '));

                if (v.className)
                    pieces.appendChild(HTML.plain(v.className + '::'));

                pieces.appendChild(HTML.plain(v.functionName + '()::'));
                pieces.appendChild(renderVariable(v.name));

                rows.push([
                    pieces,
                    HTML.plain('='),
                    renderValue(v.value)
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(HTML.table(rows));

            function block(node) {
                var div = document.createElement('div');
                div.appendChild(node);
                return div;
            }

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(HTML.notice(globals.staticPropertiesMissing + " static properties missing...")));

            if (globals.globalVariablesMissing > 0)
                container.appendChild(block(HTML.notice(globals.globalVariablesMissing + " global variables missing...")));

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(HTML.notice(globals.staticPropertiesMissing + " static variables missing...")));

            return container;
        }

        function renderException(x) {
            if (!x)
                return HTML.italics('none');

            return HTML.inline(HTML.expandable({
                head: HTML.collect([HTML.keyword('exception'), HTML.plain(' ' + x.className)]),
                body: function () {
                    var body = document.createElement('div');
                    body.appendChild(HTML.expandable({
                        open: true, head: HTML.bold('exception'), body: function () {
                            return HTML.table([
                                [HTML.bold('code'), HTML.plain(x.code)],
                                [HTML.bold('message'), HTML.plain(x.message)],
                                [HTML.bold('location'), renderLocation(x.location, true)],
                                [HTML.bold('previous'), renderException(x.previous)]
                            ]);
                        } }));
                    body.appendChild(HTML.expandable({
                        open: true, head: HTML.bold('locals'), body: function () {
                            return renderLocals(x.locals, x.localsMissing);
                        } }));
                    body.appendChild(HTML.expandable({
                        open: true, head: HTML.bold('stack'), body: function () {
                            return renderStack(x.stack, x.stackMissing);
                        } }));
                    body.appendChild(HTML.expandable({
                        open: true, head: HTML.bold('globals'), body: function () {
                            return renderGlobals(x.globals);
                        } }));
                    body.style.padding = Settings.padding;
                    return body;
                },
                open: true
            }));
        }

        function renderLocation(location, open) {
            if (typeof open === "undefined") { open = false; }
            return HTML.inline(HTML.expandable({
                head: location ? HTML.collect([HTML.plain(location.file + ':'), renderNumber(String(location.line))]) : HTML.plain('[internal function]'),
                body: function () {
                    if (!location || !location.source)
                        return HTML.notice('no source code');

                    var wrapper = document.createElement('table');

                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;

                        var lineNumber = document.createElement('td');
                        lineNumber.appendChild(HTML.plain(String(codeLine)));
                        lineNumber.style.padding = '0';
                        lineNumber.style.paddingRight = '0.5em';
                        lineNumber.style.textAlign = 'right';
                        lineNumber.style.opacity = '0.6';

                        var code = document.createElement('td');
                        code.appendChild(HTML.plain(decodeUTF8(location.source[codeLine])));
                        code.style.padding = '0';
                        code.style.width = '100%';

                        var row = document.createElement('tr');
                        row.appendChild(lineNumber);
                        row.appendChild(code);

                        if (codeLine == location.line) {
                            code.style.backgroundColor = '#f99';
                            code.style.color = '#600';
                            code.style.borderRadius = Settings.padding;
                        }

                        wrapper.appendChild(row);
                    }

                    wrapper.style.borderSpacing = '0';
                    wrapper.style.padding = Settings.padding;
                    wrapper.style.backgroundColor = '#333';
                    wrapper.style.color = '#ddd';
                    wrapper.style.width = '100%';

                    return wrapper;
                },
                open: open
            }));
        }

        function renderString(x) {
            function doRender() {
                var span = document.createElement('span');
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
                    } else if ((code < 32 || code > 126) && char !== '\n' && char != '\t') {
                        escaped = '\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16));
                    } else {
                        escaped = undefined;
                    }

                    if (escaped !== undefined) {
                        if (buffer.length > 0)
                            span.appendChild(HTML.plain(buffer));

                        buffer = "";
                        span.appendChild(HTML.keyword(escaped));
                    } else {
                        buffer += char;
                    }
                }

                span.appendChild(HTML.plain(buffer + '"'));

                var container = document.createElement('div');
                container.style.display = 'inline-block';
                container.appendChild(span);

                if (x.bytesMissing > 0) {
                    container.appendChild(HTML.plain(' '));
                    container.appendChild(HTML.italics(x.bytesMissing + ' bytes missing...'));
                }

                return container;
            }

            var visualLength = 0;

            for (var i = 0; i < x.bytes.length; i++) {
                var code = x.bytes.charCodeAt(i);
                var isPrintable = code >= 32 && code <= 126;
                visualLength += isPrintable ? 1 : 4;
            }

            if (visualLength > 200 || x.bytes.indexOf("\n") != -1)
                return HTML.inline(HTML.expandable({ open: false, head: HTML.keyword('string'), body: doRender }));
            else
                return doRender();
        }

        function renderNumber(x) {
            var result = document.createElement('span');
            result.appendChild(HTML.plain(x));
            result.style.color = '#00f';
            return result;
        }
    }
    FailWhale.renderJSON = renderJSON;

    function decodeUTF8(utf8Bytes) {
        return decodeURIComponent(escape(utf8Bytes));
    }
})(FailWhale || (FailWhale = {}));
//# sourceMappingURL=script.js.map

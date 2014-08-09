module FailWhale {

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

            window.scrollTo(
                view1.x + view1.w >= doc1.w && view1.x != 0 ? doc2.w - view2.w : view2.x,
                view1.y + view1.h >= doc1.h && view1.y != 0 ? doc2.h - view2.h : view2.y
            );
        };
    }

    module Data {
        export var Type = {
            STRING:    'string',
            ARRAY:     'array',
            OBJECT:    'object',
            INT:       'int',
            TRUE:      'true',
            FALSE:     'false',
            NULL:      'null',
            POS_INF:   '+inf',
            NEG_INF:   '-inf',
            NAN:       'nan',
            UNKNOWN:   'unknown',
            FLOAT:     'float',
            RESOURCE:  'resource',
            EXCEPTION: 'exception'
        };

        export interface Root {
            root: Value;
            strings: String1[];
            objects: Object1[];
            arrays: Array1[];
        }
        export interface String1 {
            bytes: string;
            bytesMissing: number
        }
        export interface Array1 {
            entriesMissing: number;
            entries: {
                key: Value;
                value: Value;
            }[];
        }
        export interface Object1 {
            hash: string;
            className: string;
            properties: Property[];
            propertiesMissing: number;
        }
        export interface Variable {
            name: string;
            value: Value;
        }
        export interface Property extends Variable {
            className: string;
            access: string;
        }
        export interface StaticVariable extends Variable {
            className: string;
            functionName: string;
        }
        export interface Globals {
            staticProperties: Property[];
            staticPropertiesMissing: number;
            staticVariables: StaticVariable[];
            staticVariablesMissing: number;
            globalVariables: Variable[];
            globalVariablesMissing: number;
        }
        export interface Exception {
            locals: Variable[];
            localsMissing: number;
            globals:Globals;
            stack: Stack[];
            stackMissing: number;
            className: string;
            code: string;
            message: string;
            location: Location;
            previous: Exception;
        }
        export interface Stack {
            functionName: string;
            args: {
                value: Value;
                name: string;
                typeHint: string;
                isReference: boolean;
            }[];
            argsMissing: number;
            object: number;
            className: string;
            isStatic: boolean;
            location: Location;
        }
        export interface Value {
            type: string;
            exception: Exception;
            object: number;
            array: number;
            string: number;
            int: number;
            float: number;
            resource: {
                type: string;
                id: number;
            };
        }
        export interface Location {
            file: string;
            line: number;
            source: {[lineNo:number]: string};
        }
    }

    module Settings {
        export var fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
        export var fontSize = '10pt';
        export var padding = '0.25em';
        export var borderWidth = '0.125em';
    }

    module HTML {
        export function plain(content:string):HTMLElement {
            var span = document.createElement('span');
            span.appendChild(document.createTextNode(content));
            return span;
        }

        export function italics(t:string):Node {
            var wrapped = plain(t);
            wrapped.style.display = 'inline';
            wrapped.style.fontStyle = 'italic';
            return wrapped;
        }

        export function notice(t:string):Node {
            var wrapped = plain(t);
            wrapped.style.fontStyle = 'italic';
            wrapped.style.padding = Settings.padding;
            wrapped.style.display = 'inline-block';
            return wrapped;
        }

        export function collect(nodes:Node[]):Node {
            var x = document.createDocumentFragment();
            for (var i = 0; i < nodes.length; i++)
                x.appendChild(nodes[i]);
            return x;
        }

        export function expandable(content:{
            head:Node;
            body:() => Node;
            open:boolean;
            inline?:boolean;
        }):Node {
            var container = document.createElement('div');
            var inline = content.inline === undefined ? true : false;
            container.style.display = inline ? 'inline-table' : 'block';

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

            var body = document.createElement('div');
            body.style.borderSpacing = '0';
            body.style.padding = '0';
            body.style.backgroundColor = 'white';
            body.style.borderColor = '#eee';
            body.style.borderWidth = Settings.borderWidth;
            body.style.borderTopWidth = '0px';
            body.style.borderStyle = 'solid';
            container.appendChild(body);

            var open = content.open;

            function refresh() {
                body.innerHTML = '';

                if (open) {
                    body.appendChild(content.body());
                }

                body.style.display = open ? 'block' : 'none';
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

        export function table(data:Node[][]):Node {
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

        export function bold(content:string):Node {
            var box = plain(content);
            box.style.fontWeight = 'bold';
            return box;
        }

        export function keyword(word:string) {
            var box = plain(word);
            box.style.color = '#008';
            box.style.fontWeight = 'bold';
            return box;
        }
    }

    export function renderJSON(json:string):Node {
        var root:Data.Root = JSON.parse(json);

        var container = document.createElement('div');
        container.style.whiteSpace = 'pre';
        container.style.fontFamily = Settings.fontFamily;
        container.style.fontSize = Settings.fontSize;
        container.appendChild(renderValue(root.root));
        return container;

        function renderValue(x:Data.Value) {
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
                    var span = HTML.plain('unknown type');
                    span.style.fontStyle = 'italic';
                    return span;
                default:
                    throw "unknown type " + x.type;
            }
        }

        function renderArray(id:number):Node {
            var array = root.arrays[id];
            return HTML.expandable({
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
            });
        }

        function renderObject(object:Data.Object1):Node {
            return HTML.expandable({
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
            });
        }

        function renderStack(stack:Data.Stack[], missing:number):Node {
            function renderFunctionCall(call:Data.Stack):Node {
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

                    var arg = call.args[i];
                    if (arg.name) {
                        if (arg.typeHint) {
                            var typeHint:Node;
                            switch (arg.typeHint) {
                                case 'array':
                                case 'callable':
                                    typeHint = HTML.keyword(arg.typeHint);
                                    break;
                                default:
                                    typeHint = HTML.plain(arg.typeHint);
                            }
                            result.appendChild(typeHint);
                            result.appendChild(HTML.plain(' '));
                        }
                        if (arg.isReference) {
                            result.appendChild(HTML.plain('&'));
                        }
                        result.appendChild(renderVariable(arg.name));
                        result.appendChild(HTML.plain(' = '));
                    }

                    result.appendChild(renderValue(arg.value));
                }

                if (call.argsMissing > 0) {
                    result.appendChild(HTML.plain(', '));
                    result.appendChild(HTML.italics(call.argsMissing + ' arguments missing...'));
                }

                result.appendChild(HTML.plain(')'));

                return result;
            }

            var rows:Node[][] = [];

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
                    HTML.expandable({
                        head: HTML.plain('{main}'),
                        body: function () {
                            return HTML.notice('no source code');
                        },
                        open: false
                    }),
                    HTML.collect([])
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(HTML.table(rows));
            if (missing > 0)
                container.appendChild(HTML.notice(missing + " stack frames missing..."));

            return container;
        }

        function renderVariable(name:string):Node {
            function red(v:string) {
                var result = HTML.plain(v);
                result.style.color = '#600';
                return result;
            }

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return HTML.collect([red('$' + '{'), renderString({bytes: name, bytesMissing: 0}), red('}')])
        }

        function renderLocals(locals:Data.Variable[], missing:number):Node {
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

        function renderGlobals(globals:Data.Globals) {
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

            function block(node:Node):Node {
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

        function renderException(x:Data.Exception):Node {
            if (!x)
                return HTML.italics('none');

            return HTML.expandable({
                head: HTML.collect([HTML.keyword('exception'), HTML.plain(' ' + x.className)]),
                body: function () {

                    var body = document.createElement('div');
                    body.appendChild(HTML.expandable({
                        inline: false,
                        open: true,
                        head: HTML.bold('exception'),
                        body: function () {
                            return HTML.table([
                                [HTML.bold('code'), HTML.plain(x.code)],
                                [HTML.bold('message'), HTML.plain(x.message)],
                                [HTML.bold('location'), renderLocation(x.location, true)],
                                [HTML.bold('previous'), renderException(x.previous)]
                            ]);
                        }
                    }));
                    body.appendChild(HTML.expandable({
                        inline: false,
                        open: true,
                        head: HTML.bold('locals'),
                        body: function () {
                            return renderLocals(x.locals, x.localsMissing);
                        }
                    }));
                    body.appendChild(HTML.expandable({
                        inline: false,
                        open: true,
                        head: HTML.bold('stack'),
                        body: function () {
                            return renderStack(x.stack, x.stackMissing);
                        }
                    }));
                    body.appendChild(HTML.expandable({
                        inline: false,
                        open: true,
                        head: HTML.bold('globals'),
                        body: function () {
                            return renderGlobals(x.globals);
                        }
                    }));
                    body.style.padding = Settings.padding;
                    return body;
                },
                open: true
            });
        }

        function renderLocation(location:Data.Location, open:boolean = false):Node {
            return HTML.expandable({
                head: location
                    ? HTML.collect([HTML.plain(location.file + ':'), renderNumber(String(location.line))])
                    : HTML.plain('[internal function]'),
                body: function () {
                    if (!location || !location.source)
                        return HTML.notice('no source code');

                    var lineNumber = document.createElement('div');
                    lineNumber.style.display = 'inline-block';
                    lineNumber.style.padding = '0';
                    lineNumber.style.paddingRight = '0.5em';
                    lineNumber.style.textAlign = 'right';
                    lineNumber.style.opacity = '0.6';

                    var code = document.createElement('div');
                    code.style.display = 'inline-block';
                    code.style.padding = '0';

                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;

                        var lineDiv = document.createElement('div');
                        if (codeLine == location.line) {
                            lineDiv.style.backgroundColor = '#f99';
                            lineDiv.style.color = '#600';
                            lineDiv.style.borderRadius = Settings.padding;
                        }
                        lineNumber.appendChild(HTML.plain(String(codeLine) + "\n"));
                        lineDiv.appendChild(HTML.plain(decodeUTF8(location.source[codeLine]) + "\n"));
                        code.appendChild(lineDiv);
                    }

                    var wrapper = document.createElement('div');
                    wrapper.appendChild(lineNumber);
                    wrapper.appendChild(code);
                    wrapper.style.padding = Settings.padding;
                    wrapper.style.backgroundColor = '#333';
                    wrapper.style.color = '#ddd';

                    return  wrapper;
                },
                open: open
            });
        }

        function renderString(x:Data.String1):Node {
            function doRender():Node {
                var span = document.createElement('span');
                span.style.color = '#080';
                span.style.fontWeight = 'bold';

                var translate = {
                    '\\': '\\\\',
                    '$':  '\\$',
                    '\r': '\\r',
                    '\v': '\\v',
                    '\f': '\\f',
                    '"':  '\\"'
                };

                var buffer:string = '"';

                for (var i = 0; i < x.bytes.length; i++) {
                    var char:string = x.bytes.charAt(i);
                    var code:number = x.bytes.charCodeAt(i);

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
                return HTML.expandable({open: false, head: HTML.keyword('string'), body: doRender});
            else
                return doRender();
        }

        function renderNumber(x:string):Node {
            var result = HTML.plain(x);
            result.style.color = '#00f';
            return result;
        }
    }

    function decodeUTF8(utf8Bytes:string):string {
        return decodeURIComponent(escape(utf8Bytes));
    }
}

declare function escape(s:string):string;

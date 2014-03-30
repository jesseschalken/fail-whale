module PrettyPrinter {

    interface JSONRoot {
        root: JSONValue;
        strings: JSONString[];
        objects: JSONObject[];
        arrays: JSONArray[];
    }
    interface JSONString {
        bytes: string;
        bytesMissing: number
    }
    interface JSONArray {
        entriesMissing: number;
        entries: {
            key: JSONValue;
            value: JSONValue;
        }[];
    }
    interface JSONObject {
        hash: string;
        className: string;
        properties: JSONProperty[];
        propertiesMissing: number;
    }
    interface JSONVariable {
        name: string;
        value: JSONValue;
    }
    interface JSONProperty extends JSONVariable {
        className: string;
        access: string;
    }
    interface JSONStaticVariable extends JSONVariable {
        className: string;
        functionName: string;
    }
    interface JSONGlobals {
        staticProperties: JSONProperty[];
        staticPropertiesMissing: number;
        staticVariables: JSONStaticVariable[];
        staticVariablesMissing: number;
        globalVariables: JSONVariable[];
        globalVariablesMissing: number;
    }
    interface JSONException {
        locals: JSONVariable[];
        localsMissing: number;
        globals:JSONGlobals;
        stack: JSONStack[];
        stackMissing: number;
        className: string;
        code: string;
        message: string;
        location: JSONLocation;
        previous: JSONException;
    }
    interface JSONStack {
        functionName: string;
        args: JSONValue[];
        argsMissing: number;
        object: number;
        className: string;
        isStatic: boolean;
        location: JSONLocation;
    }
    interface JSONValue {
        type: string;
        exception: JSONException;
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
    interface JSONLocation {
        file: string;
        line: number;
        source: {[lineNo:number]: string};
    }

    var fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
    var fontSize = '10pt';
    var padding = '0.25em';
    var borderWidth = '0.125em';

    function inlineBlock(inner:Node):HTMLElement {
        var s = document.createElement('div');
        s.style.display = 'inline-block';
        s.appendChild(inner);
        return s;
    }

    function block(node:Node):HTMLElement {
        var div = document.createElement('div');
        div.appendChild(node);
        return div;
    }

    function text(text:string):Node {
        return document.createTextNode(text);
    }

    function wrap(t:string):HTMLElement {
        return inlineBlock(text(t));
    }

    function italics(text:string):Node {
        var wrapped = wrap(text);
        wrapped.style.display = 'inline';
        wrapped.style.fontStyle = 'italic';
        return wrapped;
    }

    function notice(text:string):Node {
        var wrapped = wrap(text);
        wrapped.style.fontStyle = 'italic';
        wrapped.style.padding = padding;
        return wrapped;
    }

    function collect(nodes:Node[]):Node {
        var x = document.createDocumentFragment();
        for (var i = 0; i < nodes.length; i++)
            x.appendChild(nodes[i]);
        return x;
    }

    function expandable(content:{
        head:Node;
        body:() => Node;
        open:boolean;
    }):Node {
        var container = document.createElement('div');

        var head = document.createElement('div');
        head.style.backgroundColor = '#eee';
        head.style.cursor = 'pointer';
        head.style.padding = padding;
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
        body.style.borderWidth = borderWidth;
        body.style.borderTopWidth = '0px';
        body.style.borderStyle = 'solid';
        body.style.width = '100%';
        container.appendChild(body);

        var open = content.open;

        function refresh() {
            if (open && body.innerHTML.length == 0) {
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
            open = !open;
            refresh();
        });

        return container;
    }

    function createTable(data:Node[][]):HTMLTableElement {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var i = 0; i < data.length; i++) {
            var tr = document.createElement('tr');
            table.appendChild(tr);
            for (var j = 0; j < data[i].length; j++) {
                var td = document.createElement('td');
                td.style.padding = padding;
                td.style.verticalAlign = 'baseline';
                td.appendChild(data[i][j]);
                tr.appendChild(td);
            }
        }

        return table;
    }

    function bold(content:string):Node {
        var box = wrap(content);
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = wrap(word);
        box.style.color = '#008';
        box.style.fontWeight = 'bold';
        return box;
    }

    export function renderJSON(json:string):Node {
        var root:JSONRoot = JSON.parse(json);

        var container = document.createElement('div');
        container.style.whiteSpace = 'pre';
        container.style.fontFamily = fontFamily;
        container.style.fontSize = fontSize;
        container.appendChild(renderValue(root.root));
        return container;

        function renderValue(x:JSONValue) {
            switch (x.type) {
                case 'int':
                    return renderNumber(String(x.int));
                case 'float':
                    var str = String(x.float);
                    str = x.float % 1 == 0 ? str + '.0' : str;
                    return renderNumber(str);
                case 'true':
                    return keyword('true');
                case 'false':
                    return keyword('false');
                case 'string':
                    return renderString(root.strings[x.string]);
                case 'inf':
                    return keyword('INF');
                case '-inf':
                    return collect([text('-'), keyword('INF')]);
                case 'nan':
                    return keyword('NAN');
                case 'array':
                    return renderArray(x.array);
                case 'object':
                    return renderObject(root.objects[x.object]);
                case 'exception':
                    return renderException(x.exception);
                case 'resource':
                    return collect([keyword('resource'), text(' ' + x.resource.type)]);
                case 'null':
                    return keyword('null');
                default:
                    throw "unknown type " + x.type;
            }
        }

        function renderArray(id:number):Node {
            var array = root.arrays[id];
            return inlineBlock(expandable({
                head: keyword('array'),
                body: function () {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(createTable(array.entries.map(function (x) {
                        return [
                            renderValue(x.key),
                            text('=>'),
                            renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(notice(array.entriesMissing + " entries missing..."));

                    return container;
                },
                open: false
            }));
        }

        function renderObject(object:JSONObject):Node {
            return inlineBlock(expandable({
                head: collect([keyword('object'), text(' ' + object.className)]),
                body: function () {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return notice('empty');

                    var container = document.createDocumentFragment();
                    container.appendChild(createTable(object.properties.map(function (property) {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';

                        return [
                            collect([
                                keyword(property.access),
                                text(' ' + prefix),
                                renderVariable(property.name)
                            ]),
                            text('='),
                            renderValue(property.value)
                        ];
                    })));
                    if (object.propertiesMissing > 0) {
                        container.appendChild(notice(object.propertiesMissing + " properties missing..."));
                    }
                    return  container;
                },
                open: false
            }));
        }

        function renderStack(stack:JSONStack[], missing:number):Node {
            function renderFunctionCall(call:JSONStack):Node {
                var result = document.createDocumentFragment();
                var prefix = '';
                if (call.object) {
                    result.appendChild(renderObject(root.objects[call.object]));
                    prefix += '->';
                } else if (call.className) {
                    prefix += call.className;
                    prefix += call.isStatic ? '::' : '->';
                }

                result.appendChild(text(prefix + call.functionName + '('));

                for (var i = 0; i < call.args.length; i++) {
                    if (i != 0)
                        result.appendChild(text(', '));

                    result.appendChild(renderValue(call.args[i]));
                }

                if (call.argsMissing > 0) {
                    result.appendChild(text(', '));
                    result.appendChild(italics(call.argsMissing + ' arguments missing...'));
                }

                result.appendChild(text(')'));

                return result;
            }

            var rows:Node[][] = [];

            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    text('#' + String(x + 1)),
                    renderLocation(stack[x].location),
                    renderFunctionCall(stack[x])
                ]);
            }

            if (missing == 0) {
                rows.push([
                    text('#' + String(x + 1)),
                    inlineBlock(expandable({
                        head: text('{main}'),
                        body: function () {
                            return notice('no source code');
                        },
                        open: false
                    })),
                    collect([])
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(createTable(rows));
            if (missing > 0) {
                container.appendChild(notice(missing + " stack frames missing..."));
            }
            return container;
        }

        function renderVariable(name:string):Node {
            function red(v:string) {
                var result = wrap(v);
                result.style.color = '#600';
                return result;
            }

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name)) {
                return red('$' + name);
            } else {
                return collect([red('$' + '{'), renderString({bytes: name, bytesMissing: 0}), red('}')])
            }
        }

        function renderLocals(locals:JSONVariable[], missing:number):Node {
            if (!(locals instanceof Array))
                return notice('not available');

            if (locals.length == 0 && missing == 0)
                return notice('none');

            var container = document.createDocumentFragment();
            container.appendChild(createTable(locals.map(function (local) {
                return [
                    renderVariable(local.name),
                    text('='),
                    renderValue(local.value)
                ];
            })));
            if (missing > 0)
                container.appendChild(notice(missing + " variables missing..."));
            return container;
        }

        function renderGlobals(globals:JSONGlobals) {
            if (!globals)
                return notice('not available');

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
                    pieces.appendChild(keyword('global'));
                    pieces.appendChild(text(' '));
                }
                pieces.appendChild(renderVariable(v2.name));

                rows.push([pieces, text('='), renderValue(v2.value)]);

            }

            for (var i = 0; i < staticProperties.length; i++) {
                var p = staticProperties[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(keyword(p.access));
                pieces.appendChild(text(' '));
                pieces.appendChild(text(p.className + '::'));
                pieces.appendChild(renderVariable(p.name));

                rows.push([pieces, text('='), renderValue(p.value)]);
            }

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(keyword('functionName'));
                pieces.appendChild(text(' '));

                if (v.className) {
                    pieces.appendChild(text(v.className + '::'));
                }

                pieces.appendChild(text(v.functionName + '()::'));
                pieces.appendChild(renderVariable(v.name));

                rows.push([
                    pieces,
                    text('='),
                    renderValue(v.value)
                ]);
            }

            var container = document.createDocumentFragment();
            container.appendChild(createTable(rows));

            if (globals.staticPropertiesMissing > 0)
                container.appendChild(block(notice(globals.staticPropertiesMissing + " static properties missing...")));

            if (globals.globalVariablesMissing > 0)
                container.appendChild(block(notice(globals.globalVariablesMissing + " global variables missing...")));

            if (staticVariables.length != globals.staticPropertiesMissing)
                container.appendChild(block(notice(globals.staticPropertiesMissing + " static variables missing...")));

            return container;
        }

        function renderException(x:JSONException):Node {
            if (!x)
                return italics('none');

            return inlineBlock(expandable({
                head: collect([keyword('exception'), text(' ' + x.className)]),
                body: function () {

                    var body = document.createElement('div');
                    body.appendChild(block(expandable({open: true, head: bold('exception'), body: function () {
                        return createTable([
                            [bold('code'), text(x.code)],
                            [bold('message'), text(x.message)],
                            [bold('location'), renderLocation(x.location, true)],
                            [bold('previous'), renderException(x.previous)]
                        ]);
                    }})));
                    body.appendChild(block(expandable({open: true, head: bold('locals'), body: function () {
                        return renderLocals(x.locals, x.localsMissing);
                    }})));
                    body.appendChild(block(expandable({open: true, head: bold('stack'), body: function () {
                        return renderStack(x.stack, x.stackMissing);
                    }})));
                    body.appendChild(block(expandable({open: true, head: bold('globals'), body: function () {
                        return renderGlobals(x.globals);
                    }})));
                    body.style.padding = padding;
                    return body;
                },
                open: true
            }));
        }

        function renderLocation(location:JSONLocation, open:boolean = false):Node {
            return inlineBlock(expandable({
                head: collect([text(location.file + ':'), renderNumber(String(location.line))]),
                body: function () {
                    if (!location.source)
                        return notice('no source code');

                    var inner = document.createDocumentFragment();

                    for (var codeLine in location.source) {
                        if (!location.source.hasOwnProperty(codeLine))
                            continue;

                        var lineNumber = wrap(String(codeLine));
                        lineNumber.style.width = '3em';
                        lineNumber.style.paddingRight = padding;
                        lineNumber.style.marginRight = padding;
                        lineNumber.style.textAlign = 'right';
                        lineNumber.style.color = '#999';

                        var code = wrap(location.source[codeLine]);
                        code.style.minWidth = '60em';

                        var row = block(collect([lineNumber, code]));
                        if (codeLine == location.line) {
                            row.style.backgroundColor = '#f99';
                            row.style.color = '#300';
                            row.style.borderRadius = padding;
                            lineNumber.style.color = '#933';
                        }

                        inner.appendChild(row);
                    }

                    var wrapper = block(inner);
                    wrapper.style.padding = padding;
                    wrapper.style.backgroundColor = '#333';
                    wrapper.style.color = '#eee';

                    return  wrapper;
                },
                open: open
            }));
        }

        function renderString(x:JSONString):Node {
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
                            span.appendChild(document.createTextNode(buffer));

                        buffer = "";
                        span.appendChild(keyword(escaped));
                    } else {
                        buffer += char;
                    }
                }

                span.appendChild(document.createTextNode(buffer + '"'));

                var container = document.createElement('div');
                container.style.display = 'inline-block';
                container.appendChild(span);

                if (x.bytesMissing > 0) {
                    container.appendChild(document.createTextNode(' '));
                    container.appendChild(italics(x.bytesMissing + ' bytes missing...'));
                }

                return container;
            }

            var visualLength = 0;

            for (var i = 0; i < x.bytes.length; i++) {
                var code = x.bytes.charCodeAt(i);
                var isPrintable = code >= 32 && code <= 126;
                visualLength += isPrintable ? 1 : 4;
            }

            if (visualLength > 200 || x.bytes.indexOf("\n") != -1) {
                return inlineBlock(expandable({open: false, head: keyword('bytes'), body: doRender}));
            } else {
                return doRender();
            }
        }

        function renderNumber(x:string):Node {
            var result = wrap(x);
            result.style.color = '#00f';
            return result;
        }
    }
}

interface CSSStyleDeclaration {
    MozBoxSizing:string;
}

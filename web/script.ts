module FailWhale {

    function rescroll(document:Document) {
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
            globals: Globals;
            exceptions: ExceptionData[];
        }

        export interface ExceptionData {
            stack: Stack[];
            stackMissing: number;
            className: string;
            code: string;
            message: string;
        }

        export interface Stack {
            locals: Variable[];
            localsMissing: number;
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

        export interface Resource {
            type: string;
            id: number;
        }

        export interface Value {
            type: string;
            exception: Exception;
            object: number;
            array: number;
            string: number;
            int: number;
            float: number;
            resource: Resource;
        }

        export interface Location {
            file: string;
            line: number;
            source: {[lineNo:number]: string};
        }

        export interface ValueVisitor<T> {
            visitString:    (id:number) => T;
            visitArray:     (id:number) => T;
            visitObject:    (id:number) => T;
            visitInt:       (val:number) => T;
            visitTrue:      () => T;
            visitFalse:     () => T;
            visitNull:      () => T;
            visitPosInf:    () => T;
            visitNegInf:    () => T;
            visitNaN:       () => T;
            visitUnknown:   () => T;
            visitFloat:     (val:number) => T;
            visitResource:  (val:Resource) => T;
            visitException: (val:Exception) => T;
        }

        export function visit<T>(x:Value, f:ValueVisitor<T>):T {
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
    }

    class Renderer implements Data.ValueVisitor<Node> {
        private root:Data.Root;
        private document:Document;
        private padding = '4px';

        constructor(root:Data.Root, document:Document) {
            this.root = root;
            this.document = document;
        }

        private plain(content:string, inline:boolean = true):HTMLElement {
            var span = this.document.createElement(inline ? 'span' : 'div');
            span.appendChild(this.document.createTextNode(content));
            return span;
        }

        private italics(t:string):Node {
            var wrapped = this.plain(t);
            wrapped.style.display = 'inline';
            wrapped.style.fontStyle = 'italic';
            return wrapped;
        }

        private notice(t:string):Node {
            var wrapped = this.plain(t);
            wrapped.style.fontStyle = 'italic';
            wrapped.style.padding = this.padding;
            wrapped.style.display = 'inline-block';
            return wrapped;
        }

        private collect(nodes:Node[]):Node {
            var x = this.document.createDocumentFragment();
            for (var i = 0; i < nodes.length; i++)
                x.appendChild(nodes[i]);
            return x;
        }

        private expandable(content:{
            head:Node;
            body:() => Node;
            open:boolean;
            inline?:boolean;
        }):Node {
            const container = this.document.createElement('div');
            this.reset(container);
            var inline = content.inline;
            if (inline === undefined)
                inline = true;
            if (inline)
                container.style.display = 'inline-table';

            const head = this.document.createElement('div');
            head.style.backgroundColor = '#eee';
            head.style.cursor = 'pointer';
            head.style.padding = this.padding;
            head.addEventListener('mouseenter', () => {
                head.style.backgroundColor = '#ddd';
                body.style.borderColor = '#ddd';
            });
            head.addEventListener('mouseleave', () => {
                head.style.backgroundColor = '#eee';
                body.style.borderColor = '#eee';
            });
            head.addEventListener('mousedown', (e) => {
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

            const refresh = () => {
                body.innerHTML = '';

                if (open) {
                    body.appendChild(content.body());
                }

                body.style.display = open ? 'block' : 'none';
            };

            refresh();

            head.addEventListener('click', () => {
                var scroll = rescroll(this.document);
                open = !open;
                refresh();
                scroll();
            });

            return container;
        }

        private table(data:Node[][]):Node {
            var table = this.document.createElement('table');
            table.style.borderSpacing = this.padding;
            table.style.padding = '0';
            table.style.margin = '0';

            for (var i = 0; i < data.length; i++) {
                var tr = this.document.createElement('tr');
                table.appendChild(tr);
                for (var j = 0; j < data[i].length; j++) {
                    var td = this.document.createElement('td');
                    td.style.padding = '0';
                    td.style.margin = '0';
                    td.style.verticalAlign = 'baseline';
                    td.appendChild(data[i][j]);
                    tr.appendChild(td);
                }
            }

            return table;
        }

        private bold(content:string):Node {
            var box = this.plain(content);
            box.style.fontWeight = 'bold';
            return box;
        }

        private keyword(word:string) {
            var box = this.plain(word);
            box.style.color = '#009';
            box.style.fontWeight = 'bold';
            return box;
        }

        public renderRoot() {
            var container = this.document.createElement('div');
            container.style.whiteSpace = 'pre';
            container.style.fontFamily = "'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace";
            container.style.fontSize = "10pt";
            container.style.lineHeight = '16px';
            container.appendChild(this.renderValue(this.root.root));
            return container;
        }

        private renderValue(value:Data.Value):Node {
            return Data.visit(value, this);
        }

        public visitInt(val:number) {
            return this.renderNumber(String(val));
        }

        public visitFloat(val:number) {
            var str = String(val);
            str = val % 1 == 0 ? str + '.0' : str;
            return this.renderNumber(str);
        }

        public visitTrue() {
            return this.keyword('true');
        }

        public visitFalse() {
            return this.keyword('false');
        }

        public visitString(id:number) {
            return this.renderString(this.root.strings[id]);
        }

        public visitPosInf() {
            return this.renderNumber('INF');
        }

        public visitNegInf() {
            return this.renderNumber('-INF');
        }

        public visitNaN() {
            return this.renderNumber('NAN');
        }

        public visitArray(id:number) {
            return this.renderArray(id);
        }

        public visitObject(id:number) {
            return this.renderObject(this.root.objects[id]);
        }

        public visitException(val:Data.Exception) {
            return this.renderException(val);
        }

        public visitResource(val:Data.Resource) {
            return this.collect([this.keyword('resource'), this.plain(' ' + val.type)]);
        }

        public visitNull() {
            return this.keyword('null');
        }

        public visitUnknown() {
            var span = this.plain('unknown type');
            span.style.fontStyle = 'italic';
            return span;
        }

        private renderArray(id:number):Node {
            var array = this.root.arrays[id];
            return this.expandable({
                head: this.keyword('array'),
                body: () => {
                    if (array.entries.length == 0 && array.entriesMissing == 0)
                        return this.notice('empty');

                    var container = this.document.createDocumentFragment();
                    container.appendChild(this.table(array.entries.map((x) => {
                        return [
                            this.renderValue(x.key),
                            this.plain('=>'),
                            this.renderValue(x.value)
                        ];
                    })));
                    if (array.entriesMissing > 0)
                        container.appendChild(this.notice(array.entriesMissing + " entries missing..."));

                    return container;
                },
                open: false
            });
        }

        private renderObject(object:Data.Object1):Node {
            return this.expandable({
                head: this.collect([this.keyword('new'), this.plain(' ' + object.className)]),
                body: () => {
                    if (object.properties.length == 0 && object.propertiesMissing == 0)
                        return this.notice('empty');

                    var container = this.document.createDocumentFragment();
                    container.appendChild(this.table(object.properties.map((property) => {
                        var prefix = '';
                        if (property.className != object.className)
                            prefix = property.className + '::';

                        return [
                            this.collect([
                                this.keyword(property.access),
                                this.plain(' ' + prefix),
                                this.renderVariable(property.name)
                            ]),
                            this.plain('='),
                            this.renderValue(property.value)
                        ];
                    })));

                    if (object.propertiesMissing > 0)
                        container.appendChild(this.notice(object.propertiesMissing + " properties missing..."));

                    return container;
                },
                open: false
            });
        }

        private reset(node:HTMLElement) {
            node.style.borderSpacing = '0';
        }

        private renderFunctionCall(call:Data.Stack):Node {
            var result = this.document.createElement('div');
            this.reset(result);
            result.style.padding = this.padding;
            var prefix = '';
            if (call.object) {
                var object = this.root.objects[call.object];
                result.appendChild(this.renderObject(object));
                prefix += '->';
                if (object.className !== call.className)
                    prefix += call.className + '::';
            } else if (call.className) {
                prefix += call.className;
                prefix += call.isStatic ? '::' : '->';
            }

            result.appendChild(this.plain(prefix + call.functionName));

            if (call.args instanceof Array) {
                if (call.args.length == 0 && call.argsMissing == 0) {
                    result.appendChild(this.plain('()'));
                } else {
                    result.appendChild(this.plain('( '));

                    for (var i = 0; i < call.args.length; i++) {
                        if (i != 0)
                            result.appendChild(this.plain(', '));

                        var arg = call.args[i];
                        if (arg.name) {
                            if (arg.typeHint) {
                                var typeHint:Node;
                                switch (arg.typeHint) {
                                    case 'array':
                                    case 'callable':
                                        typeHint = this.keyword(arg.typeHint);
                                        break;
                                    default:
                                        typeHint = this.plain(arg.typeHint);
                                }
                                result.appendChild(typeHint);
                                result.appendChild(this.plain(' '));
                            }
                            if (arg.isReference) {
                                result.appendChild(this.plain('&'));
                            }
                            result.appendChild(this.renderVariable(arg.name));
                            result.appendChild(this.plain(' = '));
                        }

                        result.appendChild(this.renderValue(arg.value));
                    }

                    if (call.argsMissing > 0) {
                        if (i != 0)
                            result.appendChild(this.plain(', '));
                        result.appendChild(this.italics(call.argsMissing + ' arguments missing...'));
                    }

                    result.appendChild(this.plain(' )'));
                }
            } else {
                result.appendChild(this.plain('( '));
                result.appendChild(this.italics('not available'));
                result.appendChild(this.plain(' )'));
            }

            return result;
        }

        private renderStack(stack:Data.Stack[], missing:number):Node {
            var container = this.document.createElement('div');
            this.reset(container);

            for (var x = 0; x < stack.length; x++)
                container.appendChild(this.renderLocation(stack[x], x == 0));

            if (missing > 0)
                container.appendChild(this.notice(missing + " stack frames missing..."));

            container.style.padding = this.padding;
            return container;
        }

        private renderVariable(name:string):Node {
            var red = (v:string) => {
                var result = this.plain(v);
                result.style.color = '#600';
                return result;
            };

            if (/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/.test(name))
                return red('$' + name);
            else
                return this.collect([red('$' + '{'), this.renderString({bytes: name, bytesMissing: 0}), red('}')])
        }

        private renderLocals(locals:Data.Variable[], missing:number):Node {
            if (!(locals instanceof Array))
                return this.notice('not available');

            if (locals.length == 0 && missing == 0)
                return this.notice('none');

            var container = this.document.createDocumentFragment();
            container.appendChild(this.table(locals.map((local) => {
                return [
                    this.renderVariable(local.name),
                    this.plain('='),
                    this.renderValue(local.value)
                ];
            })));

            if (missing > 0)
                container.appendChild(this.notice(missing + " variables missing..."));

            return container;
        }

        private renderGlobals(globals:Data.Globals) {
            if (!globals)
                return this.notice('not available');

            var staticVariables = globals.staticVariables;
            var staticProperties = globals.staticProperties;
            var globalVariables = globals.globalVariables;
            var rows:Node[][] = [];

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

            var block = (node:Node):Node => {
                var div = this.document.createElement('div');
                this.reset(div);
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
        }

        private renderException(x:Data.Exception):Node {
            var body = this.document.createElement('div');
            this.reset(body);

            x.exceptions.forEach((e:Data.ExceptionData) => {
                body.appendChild(this.expandable({
                    inline: false,
                    open:   true,
                    head:   this.collect([this.keyword('exception'), this.plain(' ' + e.className)]),
                    body:   () => this.collect([
                        this.table([
                            [this.bold('code'), this.plain(e.code)],
                            [this.bold('message'), this.plain(e.message)],
                        ]),
                        this.renderStack(e.stack, e.stackMissing)
                    ])
                }));
            });
            body.appendChild(this.expandable({
                inline: false,
                open:   true,
                head:   this.bold('globals'),
                body:   () => this.renderGlobals(x.globals)
            }));
            return body;
        }

        private renderLocation(frame:Data.Stack, open:boolean = false):Node {
            const location = frame.location;

            var head = this.document.createDocumentFragment();
            if (location) {
                head.appendChild(this.plain(location.file + ':'));
                head.appendChild(this.renderNumber(String(location.line)));
            } else {
                head.appendChild(this.plain('[internal function]'))
            }

            var name:string;
            if (frame.className)
                name = frame.className + '::' + frame.functionName;
            else
                name = frame.functionName;
            head.appendChild(this.plain('  ' + name));

            return this.expandable({
                inline: false,
                head:   head,
                body:   () => {
                    var container = this.document.createDocumentFragment();
                    container.appendChild(this.renderFunctionCall(frame));
                    if (frame.locals)
                        container.appendChild(this.renderLocals(frame.locals, frame.localsMissing));
                    if (location.source)
                        container.appendChild(this.renderSourceCode(location));
                    return container;
                },
                open:   open
            });
        }

        private renderSourceCode(location:Data.Location) {
            var lineNumber = this.document.createElement('div');
            this.reset(lineNumber);
            lineNumber.style.cssFloat = 'left';
            lineNumber.style.padding = this.padding;
            lineNumber.style.textAlign = 'right';
            lineNumber.style.color = '#999';
            lineNumber.style.backgroundColor = '#333';
            lineNumber.style.borderRightColor = '#666';
            lineNumber.style.borderRightWidth = '1px';
            lineNumber.style.borderRightStyle = 'dashed';
            lineNumber.style.verticalAlign = 'top';
            lineNumber.style.width = '32px';

            var code = this.document.createElement('div');
            this.reset(code);
            code.style.padding = this.padding;
            code.style.overflowX = 'auto';
            code.style.backgroundColor = '#222';
            code.style.color = '#ccc';
            code.style.verticalAlign = 'top';
            code.style.marginLeft = '32px';
            var codeDiv = this.document.createElement('div');
            this.reset(codeDiv);
            code.appendChild(codeDiv);
            codeDiv.style.display = 'inline-block';
            codeDiv.style.minWidth = '100%';

            for (var codeLine in location.source) {
                if (!location.source.hasOwnProperty(codeLine))
                    continue;

                var lineDiv = this.plain(decodeUTF8(location.source[codeLine]) + "\n", false);
                if (codeLine == location.line) {
                    lineDiv.style.backgroundColor = '#f88';
                    lineDiv.style.color = '#300';
                    lineDiv.style.borderRadius = this.padding;
                }
                lineNumber.appendChild(this.plain(String(codeLine) + "\n", false));
                codeDiv.appendChild(lineDiv);
            }

            var wrapper = this.document.createElement('div');
            this.reset(wrapper);
            wrapper.appendChild(lineNumber);
            wrapper.appendChild(code);
            return wrapper;
        }

        private renderString(x:Data.String1):Node {
            var doRender = ():Node => {
                var span = this.document.createElement('span');
                this.reset(span);
                span.style.color = '#080';
                span.style.fontWeight = 'bold';

                var translate:{[index:string]: string} = {
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
                            span.appendChild(this.plain(buffer));

                        buffer = "";
                        span.appendChild(this.keyword(escaped));
                    } else {
                        buffer += char;
                    }
                }

                span.appendChild(this.plain(buffer + '"'));

                var container = this.document.createElement('div');
                this.reset(container);
                container.style.display = 'inline-table';
                container.appendChild(span);

                if (x.bytesMissing > 0) {
                    container.appendChild(this.plain(' '));
                    container.appendChild(this.italics(x.bytesMissing + ' bytes missing...'));
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
                return this.expandable({open: false, head: this.keyword('string'), body: doRender});
            else
                return doRender();
        }

        private renderNumber(x:string):Node {
            var result = this.plain(x);
            result.style.color = '#00f';
            return result;
        }
    }

    export function render(json:Data.Root, document:HTMLDocument):Node {
        console.log(json);
        return new Renderer(json, document).renderRoot();
    }

    function decodeUTF8(utf8Bytes:string):string {
        return decodeURIComponent(escape(utf8Bytes));
    }
}

declare function escape(s:string):string;

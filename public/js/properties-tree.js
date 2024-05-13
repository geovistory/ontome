function reset() {
    location.reload();
    $("#tree-container-properties").scrollLeft(0);
    $("#tree-container-properties").scrollTop($(".svg_container").height()/2);
}

$(document).ready(function() {
    // properties tree

    (function ($){

// *********** Convert flat data into a nice tree ***************
// create a name: node map
        var url = $('#tree-container').data('url');
        $.get(url, function(data){
            // *********** Convert flat data into a nice tree ***************
            // create a name: node map
            var dataMap = data.reduce(function(map, node) {
                map[node.id] = node;
                return map;
            }, {});

            // create the tree array
            var treeData = [];
            data.forEach(function(node) {
                // add to parent
                var parent = dataMap[node.parent_id];
                if (parent) {
                    // create child array if it doesn't exist
                    (parent.children || (parent.children = []))
                    // add node to child array
                        .push(node);
                } else {
                    // parent is null or missing
                    treeData.push(node);
                }
            });

            var sTreeData = '{"name": "owl:topObjectProperty","search_name": "owl:topObjectProperty","real_id": "155","children": '+JSON.stringify(treeData)+'}';

            treeData = JSON.parse(sTreeData);

            var sqlquery = treeData;
            //basically a way to get the path to an object
            function searchTree(obj,search,path){
                if(obj.search_name === search){ //if search is found return, add the object to the path and return it
                    path.push(obj);
                    return path;
                }
                else if(obj.children || obj._children){ //if children are collapsed d3 object will have them instantiated as _children
                    var children = (obj.children) ? obj.children : obj._children;
                    for(var i=0;i<children.length;i++){
                        path.push(obj);// we assume this path is the right one
                        var found = searchTree(children[i],search,path);
                        if(found){// we were right, this should return the bubbled-up path from the first if statement
                            return found;
                        }
                        else{//we were wrong, remove this parent from the path and continue iterating
                            path.pop();
                        }
                    }
                }
                else{//not the right object, return false so it will continue to iterate in the loop
                    return false;
                }
            }

            function extract_select2_data(node,leaves,index){
                if (node.children){
                    for(var i = 0;i<node.children.length;i++){
                        index = extract_select2_data(node.children[i],leaves,index)[0];
                    }
                }
                leaves.push({id:++index,text:node.search_name});
                return [index,leaves];
            }

            var div = d3.select("#tree-container-properties")
                .append("div") // declare the tooltip div
                .attr("class", "tooltip")
                .style("opacity", 0);

            var realWidth = window.innerWidth;
            var realHeight = 6000;

            var margin = [40, 240, 40, 240],
                width = realWidth -margin[0] -margin[0],
                height = realHeight -margin[0] -margin[2];

            var i = 0,
                duration = 750,
                root,
                select2_data;

            var diameter = 4000;

            var tree = d3.layout.tree()
                .size([height, width]);

            var diagonal = d3.svg.diagonal()
                .projection(function(d) { return [d.y, d.x]; });


            var svg = d3.select("#tree-container-properties").append("svg")
                .attr("class","svg_container")
                .attr("width", width)
                .attr("height", height)
                .append("g")
                .attr("class","drawarea")
                .append("g")
                .attr("transform", "translate(" + margin[3] + "," + margin[0] + ")");

            //recursively collapse children
            function collapse(d) {
                if (d.children) {
                    d._children = d.children;
                    d._children.forEach(collapse);
                    d.children = null;
                }
            }

            // Toggle children on click.
            function click(d) {
                if (d.children) {
                    d._children = d.children;
                    d.children = null;
                }
                else{
                    d.children = d._children;
                    d._children = null;
                }
                update(d);
            }

            function openPaths(paths){
                for(var i =0;i<paths.length;i++){
                    if(paths[i].id !== "1"){//i.e. not root
                        paths[i].class = 'found';
                        if(paths[i]._children){ //if children are hidden: open them, otherwise: don't do anything
                            paths[i].children = paths[i]._children;
                            paths[i]._children = null;
                        }
                        update(paths[i]);
                    }
                }
            }

            root = sqlquery;
            select2_data = extract_select2_data(sqlquery,[],0)[1];
            root.x0 = height / 2;
            root.y0 = 0;

            function collapse(d) {
                if (d.children) {
                    d._children = d.children;
                    d._children.forEach(collapse);
                    d.children = null;
                }
            }
            root.children.forEach(collapse);
            update(root);
            $("#search").select2({
                data: select2_data,
                //containerCssClass: "search"
            });

            //attach search box listener
            $('#search').on('select2:select', function (evt) {
                selectionMade = true;
                console.log("selection made: '" + evt.params.data.text + "'");
                var paths = searchTree(root,evt.params.data.text,[]);
                if(typeof(paths) !== "undefined"){
                    openPaths(paths);
                }
                else{
                    alert(evt.params.data.text+" not found!");
                }
            });

            d3.select(self.frameElement).style("height", "5000px");

            function update(source) {
                // Compute the new tree layout.
                var nodes = tree.nodes(root).reverse(),
                    links = tree.links(nodes);

                // Normalize for fixed-depth.
                nodes.forEach(function(d) { d.y = d.depth * 180; });

                // Update the nodes
                var node = svg.selectAll("g.node")
                    .data(nodes, function(d) { return d.id || (d.id = ++i); });

                // Enter any new nodes at the parent's previous position.
                var nodeEnter = node.enter().append("g")
                    .attr("class", "node")
                    .attr("transform", function(d) { return "translate(" + source.y0 + "," + source.x0 + ")"; });

                nodeEnter.append("circle")
                    .attr("r", 1e-6)
                    .attr("stroke", function(d) {

                        return d.namespace_color; //red

                    })
                    .attr("fill", function(d) { return d._children ? d.namespace_color : "#fff"; })
                    .on("click", click);

                nodeEnter.append("text")
                    .attr("x", function(d) { return d.children || d._children ? -10 : 10; })
                    .attr("dy", ".35em")
                    .attr("text-anchor", function(d) { return d.children || d._children ? "end" : "start"; })
                    .text(function(d) { return d.name; })
                    .style("fill-opacity", 1e-6)
                    .call(wrap, 125)
                    .on("click",function(d){window.open('property/'+d.real_id,'_blank');});

                // Transition nodes to their new position.
                var nodeUpdate = node.transition()
                    .duration(duration)
                    .attr("transform", function(d) { return "translate(" + d.y + "," + d.x + ")"; });

                nodeUpdate.select("circle")
                    .attr("r", 4.5)
                    .style("fill", function(d) {
                        if(d.class === "found"){
                            return "#ff4136"; //red
                        }
                        else if(d._children){
                            if(d.namespaces === "http://www.cidoc-crm.org/cidoc-crm/6-2/"){
                                return "lightsteelblue";
                            }
                            else if(d.namespaces === "http://hist.org/"){
                                return "lightgreen";
                            }
                            else if(d.namespaces === "http://symogih.org/ontology/1-2/"){
                                return "lightGoldenRod";
                            }
                            else if(d.namespaces === "http://iflastandards.info/ns/fr/frbr/frbroo/2-4"){
                                return "pink";
                            }
                        }
                        else{
                            return "#fff";
                        }
                    })
                    .style("stroke", function(d) {
                        if(d.class === "found"){
                            return "#ff4136"; //red
                        }
                    });

                nodeUpdate.select("text")
                    .style("fill-opacity", 1);

                // Transition exiting nodes to the parent's new position.
                var nodeExit = node.exit().transition()
                    .duration(duration)
                    .attr("transform", function(d) { return "translate(" + source.y + "," + source.x + ")"; })
                    .remove();

                nodeExit.select("circle")
                    .attr("r", 1e-6);

                nodeExit.select("text")
                    .style("fill-opacity", 1e-6);

                // Update the linksâ€¦
                var link = svg.selectAll("path.link")
                    .data(links, function(d) { return d.target.id; });

                // Enter any new links at the parent's previous position.
                link.enter().insert("path", "g")
                    .attr("class", "link")
                    .attr("d", function(d) {
                        var o = {x: source.x0, y: source.y0};
                        return diagonal({source: o, target: o});
                    });

                // Transition links to their new position.
                link.transition()
                    .duration(duration)
                    .attr("d", diagonal)
                    .style("stroke",function(d){
                        if(d.target.class==="found"){
                            return "#ff4136";
                        }
                    });

                // Transition exiting nodes to the parent's new position.
                link.exit().transition()
                    .duration(duration)
                    .attr("d", function(d) {
                        var o = {x: source.x, y: source.y};
                        return diagonal({source: o, target: o});
                    })
                    .remove();

                // Stash the old positions for transition.
                nodes.forEach(function(d) {
                    d.x0 = d.x;
                    d.y0 = d.y;
                });

                // call zoom fonction
                d3.select("svg")
                    .call(d3.behavior.zoom()
                        .scaleExtent([0.5, 10])
                        .on("zoom", zoom));
            }

            function zoom() {
                var scale = d3.event.scale,
                    translation = d3.event.translate,
                    tbound = -height * scale,
                    bbound = height * scale,
                    lbound = (-width + margin[1]) * scale,
                    rbound = (width - margin[3]) * scale;
                // limit translation to thresholds
                translation = [
                    Math.max(Math.min(translation[0], rbound), lbound),
                    Math.max(Math.min(translation[1], bbound), tbound)
                ];
                d3.select(".drawarea")
                    .attr("transform", "translate(" + translation + ")" +
                        " scale(" + scale + ")");
            }

            // Toggle children on click.
            function click(d) {
                if (d.children) {
                    d._children = d.children;
                    d.children = null;
                } else {
                    d.children = d._children;
                    d._children = null;
                }
                update(d);
            }

            function wrap(text, width) {
                text.each(function() {
                    var text = d3.select(this),
                        words = text.text().split(/\s+/).reverse(),
                        word,
                        line = [],
                        lineNumber = 0,
                        lineHeight = 1.1, // ems
                        y = text.attr("y"),
                        dy = parseFloat(text.attr("dy")),
                        tspan = text.text(null).append("tspan").attr("x", function(d) { return d.children || d._children ? -10 : 10; }).attr("y", y).attr("dy", dy + "em");
                    while (word = words.pop()) {
                        line.push(word);
                        tspan.text(line.join(" "));
                        if (tspan.node().getComputedTextLength() > width) {
                            line.pop();
                            tspan.text(line.join(" "));
                            line = [word];
                            tspan = text.append("tspan").attr("x", function(d) { return d.children || d._children ? -10 : 10; }).attr("y", y).attr("dy", ++lineNumber * lineHeight/2.5 + dy + "em").text(word);
                        }
                    }
                });
            }
        }, "json");

    })(jQuery);

});
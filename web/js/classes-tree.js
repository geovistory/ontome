function reset() {
    location.reload();
    $("#tree-container").scrollLeft(0);
}

$(document).ready(function() {

// *********** Convert flat data into a nice tree ***************
// create a name: node map
    var url = $('#tree-container').data('url');
    var pageName = location.href.split("/").slice(-1).pop();
    var nodeLink = 'class';
    if(pageName == 'properties-tree') {
        nodeLink = 'property';
    }
    var nodeLink = 'class';

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

        var sTreeData = '{"name": "owl:Thing","search_name": "owl:Thing","real_id": "214", "namespace_color":"black", "children": '+JSON.stringify(treeData)+'}';


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
        //treeJSON = d3.json("data.json", function(error, treeData) {

        // Calculate total nodes, max label length
        var totalNodes = 0;
        var maxLabelLength = 0;

        var selectedNode = null;

        // panning variable
        var panSpeed = 200;

        // Misc. variables
        var i = 0;
        var duration = 750;
        var root;
        var select2_data;

        // size of the diagram
        var realWidth = window.innerWidth;
        var realHeight = window.innerHeight;

        var margin = [20, 120, 20, 120],
            viewerWidth = realWidth -margin[0] -margin[0],
            viewerHeight = realHeight -margin[0] -margin[2];

        var tree = d3.layout.tree()
            .size([viewerHeight, viewerWidth]);

        // define a d3 diagonal projection for use by the node paths later on.
        var diagonal = d3.svg.diagonal()
            .projection(function(d) {
                return [d.y, d.x];
            });

        // A recursive helper function for performing some setup by walking through all nodes

        function visit(parent, visitFn, childrenFn) {
            if (!parent) return;

            visitFn(parent);

            var children = childrenFn(parent);
            if (children) {
                var count = children.length;
                for (var i = 0; i < count; i++) {
                    visit(children[i], visitFn, childrenFn);
                }
            }
        }

        // Call visit function to establish maxLabelLength
        visit(treeData, function(d) {
            totalNodes++;
            maxLabelLength = Math.max(d.name.length, maxLabelLength);

        }, function(d) {
            return d.children && d.children.length > 0 ? d.children : null;
        });


        // sort the tree according to the node names

        function sortTree() {
            tree.sort(function(a, b) {
                return b.name.toLowerCase() < a.name.toLowerCase() ? 1 : -1;
            });
        }
        // Sort the tree initially incase the JSON isn't in a sorted order.
        sortTree();

        function pan(domNode, direction) {
            var speed = panSpeed;
            if (panTimer) {
                clearTimeout(panTimer);
                translateCoords = d3.transform(svgGroup.attr("transform"));
                if (direction == 'left' || direction == 'right') {
                    translateX = direction == 'left' ? translateCoords.translate[0] + speed : translateCoords.translate[0] - speed;
                    translateY = translateCoords.translate[1];
                } else if (direction == 'up' || direction == 'down') {
                    translateX = translateCoords.translate[0];
                    translateY = direction == 'up' ? translateCoords.translate[1] + speed : translateCoords.translate[1] - speed;
                }
                scaleX = translateCoords.scale[0];
                scaleY = translateCoords.scale[1];
                scale = zoomListener.scale();
                svgGroup.transition().attr("transform", "translate(" + translateX + "," + translateY + ")scale(" + scale + ")");
                d3.select(domNode).select('g.node').attr("transform", "translate(" + translateX + "," + translateY + ")");
                zoomListener.scale(zoomListener.scale());
                zoomListener.translate([translateX, translateY]);
                panTimer = setTimeout(function() {
                    pan(domNode, speed, direction);
                }, 50);
            }
        }

        // Define the zoom function for the zoomable tree

        function zoom() {
            svgGroup.attr("transform", "translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
        }


        // define the zoomListener which calls the zoom function on the "zoom" event constrained within the scaleExtents
        var zoomListener = d3.behavior.zoom().scaleExtent([0.1, 3]).on("zoom", zoom);

        // define the baseSvg, attaching a class for styling and the zoomListener
        var baseSvg = d3.select("#tree-container").append("svg")
            .attr("width", viewerWidth)
            .attr("height", viewerHeight)
            .attr("class", "overlay")
            .call(zoomListener);


        // Helper functions for collapsing and expanding nodes.

        function collapse(d) {
            if (d.children) {
                d._children = d.children;
                d._children.forEach(collapse);
                d.children = null;
            }
        }

        function expand(d) {
            if (d._children) {
                d.children = d._children;
                d.children.forEach(expand);
                d._children = null;
            }
        }

        var overCircle = function(d) {
            selectedNode = d;
            updateTempConnector();
        };
        var outCircle = function(d) {
            selectedNode = null;
            updateTempConnector();
        };

        // Function to center node when clicked/dropped so node doesn't get lost when collapsing/moving with large amount of children.

        function centerNode(source) {
            scale = zoomListener.scale();
            x = -source.y0;
            y = -source.x0;
            x = x * scale + viewerWidth / 4;
            y = y * scale + viewerHeight / 4;
            d3.select('g').transition()
                .duration(duration)
                .attr("transform", "translate(" + x + "," + y + ")scale(" + scale + ")");
            zoomListener.scale(scale);
            zoomListener.translate([x, y]);
        }

        // Toggle children function

        function toggleChildren(d) {
            if (d.children) {
                d._children = d.children;
                d.children = null;
            } else if (d._children) {
                d.children = d._children;
                d._children = null;
            }
            return d;
        }

        // Toggle children on click.

        function click(d) {
            if (d3.event.defaultPrevented) return; // click suppressed
            d = toggleChildren(d);
            update(d);
            centerNode(d);
        }

        function update(source) {
            // Compute the new height, function counts total children of root node and sets tree height accordingly.
            // This prevents the layout looking squashed when new nodes are made visible or looking sparse when nodes are removed
            // This makes the layout more consistent.
            var levelWidth = [1];
            var childCount = function(level, n) {

                if (n.children && n.children.length > 0) {
                    if (levelWidth.length <= level + 1) levelWidth.push(0);

                    levelWidth[level + 1] += n.children.length;
                    n.children.forEach(function(d) {
                        childCount(level + 1, d);
                    });
                }
            };
            childCount(0, root);
            var newHeight = d3.max(levelWidth) * 50; // 50 pixels per line
            tree = tree.size([newHeight, viewerWidth]);

            // Compute the new tree layout.
            var nodes = tree.nodes(root).reverse(),
                links = tree.links(nodes);

            // Set widths between levels based on maxLabelLength.
            nodes.forEach(function(d) {
                d.y = (d.depth * (maxLabelLength * 5)); //maxLabelLength * 5px
                // alternatively to keep a fixed scale one can set a fixed depth per level
                // Normalize for fixed-depth by commenting out below line
                // d.y = (d.depth * 500); //500px per level.
            });

            // Update the nodes
            node = svgGroup.selectAll("g.node")
                .data(nodes, function(d) {
                    return d.id || (d.id = ++i);
                });

            // Enter any new nodes at the parent's previous position.
            var nodeEnter = node.enter().append("g")
                .attr("class", "node")
                .attr("transform", function(d) {
                    return "translate(" + source.y0 + "," + source.x0 + ")";
                })
                .on('click', click);

            nodeEnter.append("circle")
                .attr('class', 'nodeCircle')
                .attr("r", 0)
                .attr("stroke", function(d) {

                    return d.namespace_color;

                })
                .style("fill", function(d) {
                    return d._children ? d.namespace_color : "#fff";
                });

            nodeEnter.append("text")
                .attr("x", function(d) {
                    return d.children || d._children ? -10 : 10;
                })
                .attr("dy", ".35em")
                .attr('class', 'nodeText')
                .attr("text-anchor", function(d) {
                    return d.children || d._children ? "end" : "start";
                })
                .text(function(d) {
                    return d.name;
                })
                .style("fill-opacity", 0)
                .call(wrap, 125)
                .on("click",function(d){window.open(nodeLink+'/'+d.real_id,'_blank');});

            // phantom node to give us mouseover in a radius around it
            nodeEnter.append("circle")
                .attr('class', 'ghostCircle')
                .attr("r", 30)
                .attr("opacity", 0.2) // change this to zero to hide the target area
                .style("fill", "red")
                .attr('pointer-events', 'mouseover')
                .on("mouseover", function(node) {
                    overCircle(node);
                })
                .on("mouseout", function(node) {
                    outCircle(node);
                });

            // Update the text to reflect whether node has children or not.
            node.select('text')
                .attr("x", function(d) {
                    return d.children || d._children ? -10 : 10;
                })
                .attr("text-anchor", function(d) {
                    return d.children || d._children ? "end" : "start";
                })
                .text(function(d) {
                    return d.name;
                });

            // Change the circle fill depending on whether it has children and is collapsed
            node.select("circle.nodeCircle")
                .attr("r", 4.5)
                .style("fill", function(d) {
                    return d._children ? "lightsteelblue" : "#fff";
                });

            // Transition nodes to their new position.
            var nodeUpdate = node.transition()
                .duration(duration)
                .attr("transform", function(d) {
                    return "translate(" + d.y + "," + d.x + ")";
                });

            // Fade the text in
            nodeUpdate.select("text")
                .style("fill-opacity", 1);

            //red color for node found by search function
            nodeUpdate.select("circle")
                .attr("r", 4.5)
                .style("fill", function(d) {
                    if(d.class === "found"){
                        return "#ff4136"; //red
                    }
                    else if(d._children){
                        return d.namespace_color;
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



            // Transition exiting nodes to the parent's new position.
            var nodeExit = node.exit().transition()
                .duration(duration)
                .attr("transform", function(d) {
                    return "translate(" + source.y + "," + source.x + ")";
                })
                .remove();

            nodeExit.select("circle")
                .attr("r", 0);

            nodeExit.select("text")
                .style("fill-opacity", 0);

            // Update the links
            var link = svgGroup.selectAll("path.link")
                .data(links, function(d) {
                    return d.target.id;
                });

            // Enter any new links at the parent's previous position.
            link.enter().insert("path", "g")
                .attr("class", "link")
                .attr("d", function(d) {
                    var o = {
                        x: source.x0,
                        y: source.y0
                    };
                    return diagonal({
                        source: o,
                        target: o
                    });
                });

            // Transition links to their new position.
            link.transition()
                .duration(duration)
                .attr("d", diagonal);

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
                    var o = {
                        x: source.x,
                        y: source.y
                    };
                    return diagonal({
                        source: o,
                        target: o
                    });
                })
                .remove();



            // Stash the old positions for transition.
            nodes.forEach(function(d) {
                d.x0 = d.x;
                d.y0 = d.y;
            });
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

        // Append a group which holds all nodes and which the zoom Listener can act upon.
        var svgGroup = baseSvg.append("g");

        // Define the root
        root = treeData;
        select2_data = extract_select2_data(sqlquery,[],0)[1];
        root.x0 = viewerHeight / 2;
        root.y0 = 0;

        // Layout the tree initially and center on the root node.
        root.children.forEach(collapse);
        update(root);
        centerNode(root);

        $("#search").select2({
            data: select2_data
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

});
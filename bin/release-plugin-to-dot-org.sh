#!/bin/sh

CURRENT_PATH=`pwd`

# Pull down the SVN repository.
echo "Pulling down the SVN repository for hooks-reference"
SVN_WP_OPENAPI=/tmp/svn-hooks-reference
svn co https://plugins.svn.wordpress.org/hooks-reference/ $SVN_WP_OPENAPI
cd  $SVN_WP_OPENAPI

# Get the tagged version to release.
echo "Please enter the version number to release to wordpress.org, for example, 1.0.0: "
read -r VERSION

# Empty trunk/.
rm -rf trunk
mkdir trunk

# Download and unzip the plugin into trunk/.
echo "Downloading and unzipping the plugin"
PLUGIN_URL=https://github.com/moon0326/hooks-reference/releases/download/v${VERSION}/hooks-reference.zip
curl -Lo hooks-reference.zip $PLUGIN_URL
unzip hooks-reference.zip -d trunk
rm hooks-reference.zip

# Add files in trunk/ to SVN.
cd trunk
svn add --force .
cd ..

# Commit the changes, which will automatically release the plugin to wordpress.org.
echo "Checking in the new version"
svn ci -m "Release v${VERSION}"

# Tag the release
echo "Tagging the release"
svn cp trunk tags/$VERSION
svn ci -m "Tagging v${VERSION}"

# Clean up.
cd ..
rm -rf svn-hooks-reference

cd $CURRENT_PATH